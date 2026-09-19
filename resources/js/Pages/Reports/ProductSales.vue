<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  products:   { type: Array,  required: true },
  categories: { type: Array,  required: true },
  customers:  { type: Array,  required: true },
  filters:    { type: Object, required: true },
  summary:    { type: Object, required: true },
  store:      { type: Object, default: () => ({}) },
});

// ── Filters State ─────────────────────────────────────────────────────────────
const fromDate         = ref(props.filters.from_date || '');
const toDate           = ref(props.filters.to_date || '');
const selectedCategory = ref(props.filters.category_id || '');
const selectedCustomer = ref(props.filters.customer_id || '');
const searchQuery      = ref(props.filters.search || '');
const activePreset     = ref('');

// ── Expanded rows for drill-down ──────────────────────────────────────────────
const expandedProductId = ref(null);
const selectedIndex     = ref(-1);
const tableRef          = ref(null);

// ── Currency & Number formatters ──────────────────────────────────────────────
const formatCurrency = (val) =>
  new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(val || 0);

const formatNumber = (val) =>
  new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val || 0);

// ── Date Presets ──────────────────────────────────────────────────────────────
const applyPreset = (preset) => {
  activePreset.value = preset;
  const now = new Date();

  const toYMD = (d) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  if (preset === 'all') {
    fromDate.value = '';
    toDate.value = '';
  } else if (preset === 'today') {
    fromDate.value = toYMD(now);
    toDate.value = toYMD(now);
  } else if (preset === 'yesterday') {
    const y = new Date(now);
    y.setDate(y.getDate() - 1);
    fromDate.value = toYMD(y);
    toDate.value = toYMD(y);
  } else if (preset === 'this_week') {
    const d = new Date(now);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday
    const monday = new Date(d.setDate(diff));
    fromDate.value = toYMD(monday);
    toDate.value = toYMD(now);
  } else if (preset === 'this_month') {
    fromDate.value = toYMD(new Date(now.getFullYear(), now.getMonth(), 1));
    toDate.value = toYMD(now);
  } else if (preset === 'last_month') {
    fromDate.value = toYMD(new Date(now.getFullYear(), now.getMonth() - 1, 1));
    toDate.value = toYMD(new Date(now.getFullYear(), now.getMonth(), 0));
  } else if (preset === 'this_fy') {
    const curYear = now.getFullYear();
    const curMonth = now.getMonth(); // 0-indexed, 3 = April
    const fyStartYear = curMonth >= 3 ? curYear : curYear - 1;
    fromDate.value = `${fyStartYear}-04-01`;
    toDate.value = `${fyStartYear + 1}-03-31`;
  }

  applyFilters();
};

// ── Submit Server Filter ──────────────────────────────────────────────────────
const applyFilters = () => {
  router.get(
    route('reports.product-sales'),
    {
      from_date: fromDate.value,
      to_date: toDate.value,
      category_id: selectedCategory.value || undefined,
      customer_id: selectedCustomer.value || undefined,
      search: searchQuery.value || undefined,
    },
    {
      preserveState: true,
      preserveScroll: true,
    }
  );
};

const resetFilters = () => {
  fromDate.value = '';
  toDate.value = '';
  selectedCategory.value = '';
  selectedCustomer.value = '';
  searchQuery.value = '';
  activePreset.value = '';
  router.get(route('reports.product-sales'), {}, { preserveState: false });
};

// ── Client-side Live Filtering ────────────────────────────────────────────────
const filteredProducts = computed(() => {
  let list = props.products;
  if (!searchQuery.value.trim()) return list;
  const q = searchQuery.value.toLowerCase();
  return list.filter(
    (p) =>
      p.product_name.toLowerCase().includes(q) ||
      (p.product_sku && p.product_sku.toLowerCase().includes(q)) ||
      (p.category_name && p.category_name.toLowerCase().includes(q))
  );
});

// Dynamic totals calculated based on client-filtered list
const dynamicTotals = computed(() => {
  const list = filteredProducts.value;
  const grossQty = list.reduce((s, p) => s + (p.gross_quantity || 0), 0);
  const returnQty = list.reduce((s, p) => s + (p.return_quantity || 0), 0);
  const netQty = list.reduce((s, p) => s + (p.net_quantity || 0), 0);
  const taxable = list.reduce((s, p) => s + (p.taxable_amount || 0), 0);
  const gst = list.reduce((s, p) => s + (p.gst_amount || 0), 0);
  const netRevenue = list.reduce((s, p) => s + (p.net_amount || 0), 0);
  const totalCost = list.reduce((s, p) => s + (p.total_cost || 0), 0);
  const profit = list.reduce((s, p) => s + (p.total_profit || 0), 0);
  const marginPct = totalCost > 0 ? Number(((profit / totalCost) * 100).toFixed(1)) : 0;

  return {
    count: list.length,
    grossQty,
    returnQty,
    netQty,
    taxable,
    gst,
    netRevenue,
    totalCost,
    profit,
    marginPct,
  };
});

// ── Drill-Down Row Toggle ─────────────────────────────────────────────────────
const toggleDrilldown = (product) => {
  if (expandedProductId.value === product.product_id) {
    expandedProductId.value = null;
  } else {
    expandedProductId.value = product.product_id;
  }
};

// ── Tally Keyboard Navigation ─────────────────────────────────────────────────
const scrollToSelected = () => {
  if (!tableRef.value || selectedIndex.value === -1) return;
  const row = tableRef.value.querySelector(`tr[data-index="${selectedIndex.value}"]`);
  row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

const handleKeydown = (e) => {
  // If user is focused on an input or select, skip table keyboard shortcuts
  const tagName = e.target.tagName.toLowerCase();
  if (tagName === 'input' || tagName === 'select' || tagName === 'textarea') {
    return;
  }

  const len = filteredProducts.value.length;
  if (!len) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = selectedIndex.value === -1 ? 0 : Math.min(selectedIndex.value + 1, len - 1);
    nextTick(scrollToSelected);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = selectedIndex.value === -1 ? len - 1 : Math.max(selectedIndex.value - 1, 0);
    nextTick(scrollToSelected);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (selectedIndex.value >= 0 && selectedIndex.value < len) {
      const p = filteredProducts.value[selectedIndex.value];
      if (p) toggleDrilldown(p);
    }
  } else if (e.key === 'Escape') {
    if (expandedProductId.value !== null) {
      expandedProductId.value = null;
    }
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});

// ── Export Actions ────────────────────────────────────────────────────────────
const downloadPdf = () => {
  const params = new URLSearchParams({
    from_date: fromDate.value || '',
    to_date: toDate.value || '',
    category_id: selectedCategory.value || '',
    customer_id: selectedCustomer.value || '',
    search: searchQuery.value || '',
  });
  window.open(route('reports.product-sales.pdf') + '?' + params.toString(), '_blank');
};

const exportCsv = () => {
  const params = new URLSearchParams({
    from_date: fromDate.value || '',
    to_date: toDate.value || '',
    category_id: selectedCategory.value || '',
    customer_id: selectedCustomer.value || '',
    search: searchQuery.value || '',
  });
  window.location.href = route('reports.product-sales.csv') + '?' + params.toString();
};

const triggerPrint = () => {
  window.print();
};
</script>

<template>
  <Head title="Product Wise Sales Report - OkayERP" />

  <AuthenticatedLayout>
    <div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

      <!-- Breadcrumb & Header Title -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-1">
            <span>Reports</span>
            <i class="bi bi-chevron-right text-[10px]"></i>
            <span class="text-indigo-600 font-semibold">Product-wise Sales Report</span>
          </div>
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-200">
              <i class="bi bi-box2-heart text-xl"></i>
            </div>
            <div>
              <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Product-wise Sales Report</h1>
              <p class="text-xs text-slate-500">Tally Item-wise Sales Register &bull; Break down sales volume, average rate, tax, and revenue</p>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            @click="exportCsv"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-sm transition active:scale-95 cursor-pointer"
            title="Export to Excel CSV"
          >
            <i class="bi bi-file-earmark-excel text-emerald-600 text-sm"></i>
            <span>Export CSV</span>
          </button>

          <button
            type="button"
            @click="downloadPdf"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 shadow-sm shadow-indigo-200 transition active:scale-95 cursor-pointer"
            title="Download PDF"
          >
            <i class="bi bi-file-earmark-pdf text-sm"></i>
            <span>Download PDF</span>
          </button>

          <button
            type="button"
            @click="triggerPrint"
            class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold shadow-sm transition active:scale-95 cursor-pointer hover:opacity-90"
            style="background-color: #1e293b; color: #ffffff; border: 1px solid #0f172a;"
            title="Print Report"
          >
            <i class="bi bi-printer text-sm" style="color: #ffffff;"></i>
            <span style="color: #ffffff; font-weight: 600;">Print</span>
          </button>
        </div>
      </div>

      <!-- Quick Preset Filter Buttons -->
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mr-1">Period:</span>
            <button
              v-for="p in [
                { id: 'all', label: 'All Dates' },
                { id: 'today', label: 'Today' },
                { id: 'yesterday', label: 'Yesterday' },
                { id: 'this_week', label: 'This Week' },
                { id: 'this_month', label: 'This Month' },
                { id: 'last_month', label: 'Last Month' },
                { id: 'this_fy', label: 'This FY' },
              ]"
              :key="p.id"
              type="button"
              @click="applyPreset(p.id)"
              :class="[
                'px-2.5 py-1 text-xs font-medium rounded-md transition cursor-pointer',
                activePreset === p.id || (!filters.from_date && !filters.to_date && p.id === 'all')
                  ? 'bg-indigo-600 text-white font-semibold shadow-sm'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              ]"
            >
              {{ p.label }}
            </button>
          </div>

          <div class="text-xs text-slate-500 font-medium">
            Active Range:
            <span class="font-bold text-slate-800">
              {{ (filters.from_date || filters.to_date) ? ((filters.from_date || 'Start') + ' to ' + (filters.to_date || 'Today')) : 'All Sales (All Time)' }}
            </span>
          </div>
        </div>

        <!-- Filter Form Controls -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3 pt-2 border-t border-slate-100">
          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">From Date</label>
            <input
              type="date"
              v-model="fromDate"
              class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">To Date</label>
            <input
              type="date"
              v-model="toDate"
              class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
            />
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Category</label>
            <select
              v-model="selectedCategory"
              class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="">All Categories</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Customer</label>
            <select
              v-model="selectedCustomer"
              class="w-full text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
            >
              <option value="">All Customers</option>
              <option v-for="cust in customers" :key="cust.id" :value="cust.id">
                {{ cust.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Search Product</label>
            <div class="relative">
              <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
              <input
                type="text"
                v-model="searchQuery"
                placeholder="Product name or SKU..."
                class="w-full pl-8 text-xs rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>
          </div>

          <div class="flex items-end gap-2">
            <button
              type="button"
              @click="applyFilters"
              class="flex-1 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-lg transition shadow-sm cursor-pointer text-center"
            >
              Apply Filter
            </button>
            <button
              type="button"
              @click="resetFilters"
              class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition cursor-pointer"
              title="Reset Filters"
            >
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- KPI Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Products Count -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
          <div class="h-11 w-11 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
            <i class="bi bi-box-seam text-xl"></i>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">Products Sold</p>
            <p class="text-xl font-bold text-slate-900">{{ dynamicTotals.count }}</p>
          </div>
        </div>

        <!-- Total Quantity Sold -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
          <div class="h-11 w-11 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
            <i class="bi bi-stack text-xl"></i>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider truncate">Net Units Sold</p>
            <p class="text-xl font-bold text-blue-600">
              {{ formatNumber(dynamicTotals.netQty) }}
              <span v-if="dynamicTotals.returnQty > 0" class="text-xs text-red-500 font-normal">
                (-{{ formatNumber(dynamicTotals.returnQty) }} ret)
              </span>
            </p>
          </div>
        </div>

        <!-- Net Total Revenue -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
          <div class="h-11 w-11 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
            <i class="bi bi-currency-rupee text-xl"></i>
          </div>
          <div class="min-w-0">
            <p class="text-[11px] font-semibold text-purple-600 uppercase tracking-wider truncate">Net Revenue</p>
            <p class="text-xl font-black text-slate-900 truncate">{{ formatCurrency(dynamicTotals.netRevenue) }}</p>
          </div>
        </div>

        <!-- Net Profit -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
          <div
            class="h-11 w-11 rounded-lg flex items-center justify-center shrink-0"
            :class="dynamicTotals.profit >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
          >
            <i :class="dynamicTotals.profit >= 0 ? 'bi bi-graph-up-arrow text-xl' : 'bi bi-graph-down-arrow text-xl'"></i>
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-1.5">
              <p class="text-[11px] font-semibold uppercase tracking-wider truncate" :class="dynamicTotals.profit >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                Net Profit
              </p>
              <span
                v-if="dynamicTotals.totalCost > 0"
                class="px-1.5 py-0.5 rounded text-[10px] font-bold"
                :class="dynamicTotals.profit >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
              >
                {{ dynamicTotals.profit >= 0 ? '+' : '' }}{{ dynamicTotals.marginPct }}%
              </span>
            </div>
            <p
              class="text-xl font-black truncate"
              :class="dynamicTotals.profit >= 0 ? 'text-emerald-700' : 'text-rose-600'"
            >
              {{ dynamicTotals.profit >= 0 ? '+' : '' }}{{ formatCurrency(dynamicTotals.profit) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tally-Style Table -->
      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" ref="tableRef">

        <!-- Table Container -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-100/80 text-slate-700 font-bold border-b border-slate-200 text-[11px] uppercase tracking-wider">
                <th class="py-3 px-3 text-center w-10">#</th>
                <th class="py-3 px-4 min-w-[180px]">Product & SKU</th>
                <th class="py-3 px-3 text-center">Unit</th>
                <th class="py-3 px-3 text-right">Gross Qty</th>
                <th class="py-3 px-3 text-right">Return</th>
                <th class="py-3 px-3 text-right">Net Qty</th>
                <th class="py-3 px-3 text-right text-amber-900 bg-amber-50/50">Purchase Price</th>
                <th class="py-3 px-3 text-right text-amber-900 bg-amber-50/50">Purchase Amt (₹)</th>
                <th class="py-3 px-3 text-right text-blue-900 bg-blue-50/50">Sale Price</th>
                <th class="py-3 px-3 text-right text-blue-900 bg-blue-50/50">Sale Amt (₹)</th>
                <th class="py-3 px-3 text-right text-emerald-900 bg-emerald-50/50">Profit (₹)</th>
                <th class="py-3 px-3 text-center w-14">Detail</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <template v-for="(p, idx) in filteredProducts" :key="p.product_id">
                <tr
                  :data-index="idx"
                  @click="selectedIndex = idx; toggleDrilldown(p)"
                  :class="[
                    'transition-colors cursor-pointer select-none',
                    selectedIndex === idx ? 'bg-indigo-50/90 font-medium' : 'hover:bg-slate-50/80',
                    expandedProductId === p.product_id ? 'bg-indigo-50/50' : ''
                  ]"
                >
                  <!-- Index -->
                  <td class="py-3 px-3 text-center text-slate-400 font-mono text-[11px]">
                    {{ idx + 1 }}
                  </td>

                  <!-- Product Name & SKU -->
                  <td class="py-3 px-4">
                    <div class="font-bold text-slate-900">{{ p.product_name }}</div>
                    <div v-if="p.product_sku" class="text-[11px] text-slate-400 font-mono">
                      SKU: {{ p.product_sku }}
                    </div>
                  </td>

                  <!-- Unit -->
                  <td class="py-3 px-3 text-center text-slate-600 font-mono">
                    {{ p.unit_type }}
                  </td>

                  <!-- Gross Qty -->
                  <td class="py-3 px-3 text-right font-mono text-slate-700">
                    {{ formatNumber(p.gross_quantity) }}
                  </td>

                  <!-- Return Qty -->
                  <td class="py-3 px-3 text-right font-mono">
                    <span v-if="p.return_quantity > 0" class="text-red-600 font-semibold">
                      -{{ formatNumber(p.return_quantity) }}
                    </span>
                    <span v-else class="text-slate-300">—</span>
                  </td>

                  <!-- Net Qty -->
                  <td class="py-3 px-3 text-right font-mono font-bold text-emerald-600">
                    {{ formatNumber(p.net_quantity) }}
                  </td>

                  <!-- Purchase Rate -->
                  <td class="py-3 px-3 text-right font-mono text-slate-700 bg-amber-50/20">
                    <span v-if="p.purchase_rate > 0" class="font-semibold text-slate-800">
                      {{ formatCurrency(p.purchase_rate) }}
                    </span>
                    <span v-else class="text-slate-400 italic text-[11px]" title="No purchase records found">—</span>
                  </td>

                  <!-- Purchase Amount (Total Cost) -->
                  <td class="py-3 px-3 text-right font-mono font-bold text-amber-950 bg-amber-50/20">
                    <span v-if="p.total_cost > 0">
                      {{ formatCurrency(p.total_cost) }}
                    </span>
                    <span v-else class="text-slate-400 italic text-[11px]">—</span>
                  </td>

                  <!-- Sale Rate (Avg) -->
                  <td class="py-3 px-3 text-right font-mono text-slate-900 font-semibold bg-blue-50/20">
                    {{ formatCurrency(p.avg_rate) }}
                  </td>

                  <!-- Sale Amount (Net Sales) -->
                  <td class="py-3 px-3 text-right font-mono font-bold text-blue-950 bg-blue-50/20">
                    {{ formatCurrency(p.net_amount) }}
                  </td>

                  <!-- Net Profit (Sale Amt - Purchase Amt) -->
                  <td class="py-3 px-3 text-right font-mono bg-emerald-50/20">
                    <span
                      class="font-black text-xs"
                      :class="p.total_profit >= 0 ? 'text-emerald-700' : 'text-rose-600'"
                    >
                      {{ p.total_profit >= 0 ? '+' : '' }}{{ formatCurrency(p.total_profit) }}
                    </span>
                  </td>

                  <!-- Drilldown Toggle Button -->
                  <td class="py-3 px-3 text-center">
                    <button
                      type="button"
                      @click.stop="toggleDrilldown(p)"
                      class="h-7 w-7 rounded-lg inline-flex items-center justify-center text-slate-400 hover:text-indigo-600 hover:bg-indigo-100 transition cursor-pointer"
                      :title="expandedProductId === p.product_id ? 'Collapse Invoices' : 'View Invoices'"
                    >
                      <i
                        class="bi bi-chevron-down text-xs transition-transform duration-200"
                        :class="{ 'rotate-180 text-indigo-600': expandedProductId === p.product_id }"
                      ></i>
                    </button>
                  </td>
                </tr>

                <!-- Expanded Drill-down Transaction Sub-Table -->
                <tr v-if="expandedProductId === p.product_id" class="bg-slate-50/90 border-b border-indigo-200">
                  <td colspan="12" class="p-4 pl-12">
                    <div class="bg-white rounded-xl border border-indigo-100 shadow-sm p-4 space-y-3">
                      <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-2">
                          <i class="bi bi-receipt-cutoff text-indigo-600"></i>
                          <span class="font-bold text-slate-800 text-xs">
                            Sales Invoices for <span class="text-indigo-600">{{ p.product_name }}</span>
                          </span>
                          <span class="text-[10px] text-slate-400 font-medium">({{ p.transactions.length }} entries)</span>
                        </div>
                        <span class="text-[11px] text-slate-500">
                          Click any invoice to view full voucher
                        </span>
                      </div>

                      <div v-if="p.transactions && p.transactions.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                          <thead>
                            <tr class="bg-slate-50 text-slate-500 text-[10px] font-bold uppercase tracking-wider border-b border-slate-200">
                              <th class="py-2 px-3">Date</th>
                              <th class="py-2 px-3">Invoice #</th>
                              <th class="py-2 px-3">Customer / Party</th>
                              <th class="py-2 px-3 text-right">Quantity</th>
                              <th class="py-2 px-3 text-right">Purchase Price</th>
                              <th class="py-2 px-3 text-right">Purchase Amt</th>
                              <th class="py-2 px-3 text-right">Sale Price</th>
                              <th class="py-2 px-3 text-right">Sale Amt</th>
                              <th class="py-2 px-3 text-right">Profit</th>
                              <th class="py-2 px-3 text-center">Action</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-100">
                            <tr
                              v-for="tx in p.transactions"
                              :key="tx.item_id"
                              class="hover:bg-indigo-50/40 transition-colors"
                            >
                              <td class="py-2 px-3 text-slate-600 font-mono text-[11px]">{{ tx.date }}</td>
                              <td class="py-2 px-3 font-semibold text-indigo-600">
                                <a :href="route('sale.show', tx.sale_id)" target="_blank" class="hover:underline flex items-center gap-1">
                                  <span>{{ tx.invoice_no }}</span>
                                  <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                </a>
                              </td>
                              <td class="py-2 px-3 text-slate-700">
                                <span class="font-medium">{{ tx.customer_name }}</span>
                              </td>
                              <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800">
                                {{ formatNumber(tx.quantity) }} {{ tx.unit_type }}
                              </td>
                              <td class="py-2 px-3 text-right font-mono text-slate-600">
                                {{ tx.purchase_rate > 0 ? formatCurrency(tx.purchase_rate) : '—' }}
                              </td>
                              <td class="py-2 px-3 text-right font-mono font-semibold text-amber-900">
                                {{ tx.purchase_amount > 0 ? formatCurrency(tx.purchase_amount) : '—' }}
                              </td>
                              <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800">
                                {{ formatCurrency(tx.price) }}
                                <span v-if="tx.discount_amount > 0" class="block text-[10px] text-amber-600 font-normal">
                                  (-{{ formatCurrency(tx.discount_amount) }} dis)
                                </span>
                              </td>
                              <td class="py-2 px-3 text-right font-mono font-bold text-slate-900">
                                {{ formatCurrency(tx.total_amount) }}
                              </td>
                              <td class="py-2 px-3 text-right font-mono font-bold" :class="tx.profit >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                                {{ tx.profit >= 0 ? '+' : '' }}{{ formatCurrency(tx.profit) }}
                              </td>
                              <td class="py-2 px-3 text-center">
                                <a
                                  :href="route('sale.show', tx.sale_id)"
                                  target="_blank"
                                  class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-800"
                                >
                                  View
                                </a>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                      <div v-else class="text-center py-4 text-xs text-slate-400">
                        No transactions recorded for this item.
                      </div>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- Empty State -->
              <tr v-if="filteredProducts.length === 0">
                <td colspan="12" class="text-center py-12 text-slate-400">
                  <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-2 text-slate-400">
                    <i class="bi bi-inbox text-xl"></i>
                  </div>
                  <p class="text-sm font-semibold text-slate-700">No Product Sales Found</p>
                  <p class="text-xs text-slate-400 mt-1">Try changing your date range, category, or search filters.</p>
                </td>
              </tr>
            </tbody>

            <!-- Grand Totals Footer Row -->
            <tfoot v-if="filteredProducts.length > 0" class="bg-slate-100 border-t-2 border-slate-300 font-bold text-slate-800">
              <tr>
                <td colspan="3" class="py-3 px-4 text-slate-900 font-black uppercase text-[11px]">
                  Grand Total ({{ dynamicTotals.count }} Products)
                </td>
                <td class="py-3 px-3 text-right font-mono text-[11px] text-slate-900">
                  {{ formatNumber(dynamicTotals.grossQty) }}
                </td>
                <td class="py-3 px-3 text-right font-mono text-[11px] text-red-600">
                  {{ dynamicTotals.returnQty > 0 ? '-' + formatNumber(dynamicTotals.returnQty) : '—' }}
                </td>
                <td class="py-3 px-3 text-right font-mono text-[11px] text-emerald-700 font-black">
                  {{ formatNumber(dynamicTotals.netQty) }}
                </td>
                <td class="py-3 px-3 text-right font-mono text-[11px] text-slate-400">—</td>
                <td class="py-3 px-3 text-right font-mono text-[11px] font-black text-amber-900">
                  {{ formatCurrency(dynamicTotals.totalCost) }}
                </td>
                <td class="py-3 px-3 text-right font-mono text-[11px] text-slate-400">—</td>
                <td class="py-3 px-3 text-right font-mono text-[11px] font-black text-blue-900">
                  {{ formatCurrency(dynamicTotals.netRevenue) }}
                </td>
                <td class="py-3 px-3 text-right font-mono text-[11px] font-black" :class="dynamicTotals.profit >= 0 ? 'text-emerald-700' : 'text-rose-600'">
                  {{ dynamicTotals.profit >= 0 ? '+' : '' }}{{ formatCurrency(dynamicTotals.profit) }}
                </td>
                <td class="py-3 px-3"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@media print {
  /* Hide non-printable elements */
  nav,
  aside,
  header,
  button,
  .no-print,
  input,
  select {
    display: none !important;
  }

  body {
    background: #ffffff !important;
  }

  table {
    border-collapse: collapse !important;
    width: 100% !important;
  }

  th,
  td {
    border: 1px solid #cbd5e1 !important;
    padding: 4px 6px !important;
  }
}
</style>
