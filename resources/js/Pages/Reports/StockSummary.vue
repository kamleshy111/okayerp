<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  products:         { type: Array,  required: true },
  categories:       { type: Array,  required: true },
  totalProducts:    { type: Number, required: true },
  totalQtyInStock:  { type: Number, required: true },
  totalCostValue:   { type: Number, required: true },
  totalRetailValue: { type: Number, required: true },
  outOfStockCount:  { type: Number, required: true },
  lowStockCount:    { type: Number, required: true },
});

// ── Filters ──────────────────────────────────────────────────────────────
const searchQuery      = ref('');
const selectedCategory = ref('');
const stockFilter      = ref('all');

const formatCurrency = (val) =>
  new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 }).format(val || 0);

const filteredProducts = computed(() => {
  let rows = props.products;
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase();
    rows = rows.filter(p =>
      p.name.toLowerCase().includes(q) ||
      (p.sku && String(p.sku).toLowerCase().includes(q)) ||
      (p.category_name && p.category_name.toLowerCase().includes(q))
    );
  }
  if (selectedCategory.value)            rows = rows.filter(p => p.category_name === selectedCategory.value);
  if (stockFilter.value === 'in_stock')  rows = rows.filter(p => p.stock_quantity > 0);
  if (stockFilter.value === 'low_stock') rows = rows.filter(p => p.stock_quantity > 0 && p.stock_quantity < 10);
  if (stockFilter.value === 'out_of_stock') rows = rows.filter(p => p.stock_quantity === 0);
  return rows;
});

const filteredTotals = computed(() => ({
  qty:  filteredProducts.value.reduce((s, p) => s + p.stock_quantity, 0),
  cost: filteredProducts.value.reduce((s, p) => s + p.cost_valuation, 0),
  ret:  filteredProducts.value.reduce((s, p) => s + p.retail_valuation, 0),
}));

const stockBadge = (qty) => {
  if (qty === 0) return { label: 'Out of Stock', cls: 'bg-red-100 text-red-700' };
  if (qty < 10)  return { label: 'Low Stock',    cls: 'bg-yellow-100 text-yellow-700' };
  return               { label: 'In Stock',     cls: 'bg-green-100 text-green-700' };
};

const clearFilters = () => {
  searchQuery.value = '';
  selectedCategory.value = '';
  stockFilter.value = 'all';
};

// ── Row selection + keyboard nav ──────────────────────────────────────────
const selectedIndex = ref(0);   // first row selected on mount
const tableRef      = ref(null);

// When filters change, reset selection to first row
watch(filteredProducts, () => {
  selectedIndex.value = 0;
  nextTick(() => scrollToSelected());
});

const selectRow = (index) => {
  selectedIndex.value = index;
};

const openRow = (product) => {
  router.visit(route('reports.stock-item-summary', product.id));
};

const scrollToSelected = () => {
  if (!tableRef.value) return;
  const row = tableRef.value.querySelector(`tr[data-index="${selectedIndex.value}"]`);
  row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

const handleKeydown = (e) => {
  const len = filteredProducts.value.length;
  if (!len) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = Math.min(selectedIndex.value + 1, len - 1);
    nextTick(scrollToSelected);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    nextTick(scrollToSelected);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    const product = filteredProducts.value[selectedIndex.value];
    if (product) openRow(product);
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  nextTick(() => scrollToSelected());
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
  <Head title="Stock Summary" />
  <AuthenticatedLayout>
    <div class="p-6 mx-auto space-y-6">

      <!-- ── Header ── -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2">
        <div>
          <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Stock Summary</h1>
          <p class="text-gray-400 text-sm mt-0.5">Inventory snapshot — quantities, rates &amp; valuations</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1.5 rounded-full font-medium">
            {{ filteredProducts.length }} / {{ totalProducts }} products
          </span>
          <!-- Keyboard hint -->
          <span class="hidden md:flex items-center gap-1.5 text-xs text-gray-400 bg-gray-50 border border-gray-200 px-3 py-1.5 rounded-full">
            <kbd class="px-1 py-0.5 bg-white border border-gray-300 rounded text-[10px] font-mono shadow-sm">↑</kbd>
            <kbd class="px-1 py-0.5 bg-white border border-gray-300 rounded text-[10px] font-mono shadow-sm">↓</kbd>
            navigate &nbsp;·&nbsp;
            <kbd class="px-1.5 py-0.5 bg-white border border-gray-300 rounded text-[10px] font-mono shadow-sm">Enter</kbd>
            open &nbsp;·&nbsp;
            Double-click to open
          </span>
        </div>
      </div>

      <!-- ── KPI Cards ── -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="col-span-2 lg:col-span-2 bg-gradient-to-br from-[#2e2c92] to-[#4c49d8] text-white p-5 rounded-2xl shadow-xl flex flex-col justify-between">
          <span class="text-xs font-medium opacity-75 uppercase tracking-wider">Total Products</span>
          <span class="text-4xl font-black mt-2">{{ totalProducts }}</span>
        </div>
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-sm border-l-4 border-l-indigo-400 flex flex-col justify-between">
          <span class="text-xs text-gray-400 uppercase tracking-wider">Total Qty</span>
          <span class="text-2xl font-extrabold text-gray-800 mt-2">{{ totalQtyInStock.toLocaleString('en-IN') }}</span>
        </div>
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-sm border-l-4 border-l-blue-400 flex flex-col justify-between">
          <span class="text-xs text-gray-400 uppercase tracking-wider">Cost Value</span>
          <span class="text-base font-extrabold text-gray-800 mt-2">{{ formatCurrency(totalCostValue) }}</span>
        </div>
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-sm border-l-4 border-l-emerald-400 flex flex-col justify-between">
          <span class="text-xs text-gray-400 uppercase tracking-wider">Retail Value</span>
          <span class="text-base font-extrabold text-gray-800 mt-2">{{ formatCurrency(totalRetailValue) }}</span>
        </div>
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-sm border-l-4 border-l-red-400 flex flex-col justify-between">
          <span class="text-xs text-gray-400 uppercase tracking-wider">Out of Stock</span>
          <span class="text-2xl font-extrabold text-red-600 mt-2">{{ outOfStockCount }}</span>
        </div>
      </div>

      <!-- ── Filters ── -->
      <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
          <div class="relative flex-1">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input
              v-model="searchQuery" type="text"
              placeholder="Search product, SKU, category…"
              class="pl-9 pr-4 py-2.5 w-full border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 transition"
            />
          </div>
          <select v-model="selectedCategory"
            class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition min-w-[170px]">
            <option value="">All Categories</option>
            <option v-for="cat in categories" :key="cat.id" :value="cat.name">{{ cat.name }}</option>
          </select>
          <div class="flex items-center gap-1.5 flex-wrap">
            <button v-for="opt in [
              { val:'all',          label:'All' },
              { val:'in_stock',     label:'In Stock' },
              { val:'low_stock',    label:'Low Stock' },
              { val:'out_of_stock', label:'Out of Stock' },
            ]" :key="opt.val" @click="stockFilter = opt.val"
              :class="[
                'px-3 py-2 rounded-xl text-xs font-semibold transition-all',
                stockFilter === opt.val ? 'bg-[#2e2c92] text-white shadow' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              ]">
              {{ opt.label }}
            </button>
          </div>
          <button v-if="searchQuery || selectedCategory || stockFilter !== 'all'"
            @click="clearFilters"
            class="text-xs text-gray-400 hover:text-red-500 transition flex items-center gap-1 whitespace-nowrap">
            <i class="bi bi-x-circle"></i> Clear
          </button>
        </div>
      </div>

      <!-- ── Table ── -->
      <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table ref="tableRef" class="w-full text-sm text-left">

            <thead class="bg-[#2e2c92] text-white text-xs uppercase tracking-wider">
              <tr style="background-color: rgba(96, 94, 238, 0.52) !important;">
                <th class="px-5 py-3 w-10 text-center">#</th>
                <th class="px-5 py-3">Product</th>
                <th class="px-5 py-3 text-center">Quantity</th>
                <th class="px-5 py-3 text-center">Unit</th>
                <th class="px-5 py-3 text-right">Rate</th>
                <th class="px-5 py-3 text-right">Value</th>
                <th class="px-5 py-3 text-center">Status</th>
              </tr>
            </thead>

            <tbody>
              <!-- Empty state -->
              <tr v-if="filteredProducts.length === 0">
                <td colspan="7" class="text-center py-16 text-gray-400">
                  <i class="bi bi-inbox text-5xl opacity-40 block mb-3"></i>
                  <p class="text-sm font-medium">No products found</p>
                </td>
              </tr>

              <!-- Data rows -->
              <tr
                v-for="(product, index) in filteredProducts"
                :key="product.id"
                :data-index="index"
                @click="selectRow(index)"
                @dblclick="openRow(product)"
                :class="[
                  'border-b border-gray-100 transition-colors duration-100 cursor-pointer select-none',
                  selectedIndex === index
                    ? 'bg-[#2e2c92] text-white ring-2 ring-inset ring-indigo-400 selected-row'
                    : product.stock_quantity === 0
                      ? 'bg-red-50/40 hover:bg-red-100/60'
                      : product.stock_quantity < 10
                        ? 'bg-yellow-50/40 hover:bg-yellow-100/60'
                        : index % 2 === 0
                          ? 'bg-white hover:bg-indigo-50/50'
                          : 'bg-gray-50/50 hover:bg-indigo-50/50'
                ]"
              >
                <!-- S.No -->
                <td class="px-5 py-3 text-center font-medium text-xs"
                  :class="selectedIndex === index ? 'text-indigo-200' : 'text-gray-400'">
                  {{ index + 1 }}
                </td>

                <!-- Product name + SKU -->
                <td class="px-5 py-3">
                  <p class="font-semibold" :class="selectedIndex === index ? 'text-white' : 'text-gray-800'">
                    {{ product.name }}
                  </p>
                  <p v-if="product.sku" class="text-[11px] font-mono mt-0.5"
                    :class="selectedIndex === index ? 'text-indigo-200' : 'text-gray-400'">
                    SKU: {{ product.sku }}
                  </p>
                </td>

                <!-- Quantity -->
                <td class="px-5 py-3 text-center">
                  <span class="text-lg font-extrabold" :class="
                    selectedIndex === index ? 'text-white' :
                    product.stock_quantity === 0 ? 'text-red-500' :
                    product.stock_quantity < 10  ? 'text-yellow-600' : 'text-gray-800'
                  ">{{ product.stock_quantity }}</span>
                </td>

                <!-- Unit -->
                <td class="px-5 py-3 text-center text-xs font-medium uppercase"
                  :class="selectedIndex === index ? 'text-indigo-200' : 'text-gray-500'">
                  {{ product.unit_type || '—' }}
                </td>

                <!-- Rate -->
                <td class="px-5 py-3 text-right font-semibold"
                  :class="selectedIndex === index ? 'text-white' : 'text-gray-700'">
                  {{ formatCurrency(product.selling_price) }}
                </td>

                <!-- Value -->
                <td class="px-5 py-3 text-right font-bold"
                  :class="selectedIndex === index ? 'text-emerald-200' : 'text-emerald-700'">
                  {{ formatCurrency(product.retail_valuation) }}
                </td>

                <!-- Status -->
                <td class="px-5 py-3 flex justify-center items-center">
                  <span v-if="selectedIndex !== index"
                    :class="['text-xs font-semibold px-2.5 py-1 rounded-full', stockBadge(product.stock_quantity).cls]">
                    {{ stockBadge(product.stock_quantity).label }}
                  </span>
                  <span v-else class="text-xs font-semibold px-2.5 py-1 rounded-full bg-white/20 text-white">
                    {{ stockBadge(product.stock_quantity).label }}
                  </span>
                </td>
              </tr>
            </tbody>

            <!-- Totals footer -->
            <tfoot v-if="filteredProducts.length > 0">
              <tr class="bg-[#2e2c92] text-white">
                <td colspan="2" class="px-5 py-3 text-sm font-bold uppercase tracking-wide">
                  Total — {{ filteredProducts.length }} products
                </td>
                <td class="px-5 py-3 text-center font-extrabold text-lg">
                  {{ filteredTotals.qty.toLocaleString('en-IN') }}
                </td>
                <td class="px-5 py-3"></td>
                <td class="px-5 py-3"></td>
                <td class="px-5 py-3 text-right font-extrabold text-emerald-300">
                  {{ formatCurrency(filteredTotals.ret) }}
                </td>
                <td class="px-5 py-3"></td>
              </tr>
            </tfoot>

          </table>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
/* Smooth row highlight transition */
tbody tr {
  transition: background-color 0.1s ease, color 0.1s ease;
}

/* Selected row left indicator */
tr.selected-row td:first-child {
  border-left: 3px solid #a5b4fc;
}
</style>
