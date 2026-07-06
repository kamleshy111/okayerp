<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  product:      { type: Object, required: true },
  storeName:    { type: String, required: true },
  monthLabel:   { type: String, required: true },
  monthName:    { type: String, required: true },
  year:         { type: Number, required: true },
  month:        { type: Number, required: true },
  openingQty:   { type: Number, required: true },
  openingValue: { type: Number, required: true },
  rows:         { type: Array,  required: true },
  totalInQty:   { type: Number, required: true },
  totalInVal:   { type: Number, required: true },
  totalOutQty:  { type: Number, required: true },
  totalOutVal:  { type: Number, required: true },
  closingQty:   { type: Number, required: true },
  closingVal:   { type: Number, required: true },
});

// ── Formatters ──────────────────────────────────────────────────────────────
const fmtNum = (n) =>
  new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);

const unit = props.product.unit_type || '';
const qty  = (n) => (n > 0 ? `${n}${unit ? ' ' + unit : ''}` : '—');

// ── Row selection & keyboard nav ────────────────────────────────────────────
const selectedIndex = ref(-1);
const tableRef      = ref(null);

const selectRow = (i) => {
  if (typeof window !== 'undefined' && window.innerWidth < 768) {
    openInvoice(props.rows[i]);
    return;
  }
  if (selectedIndex.value === i) {
    openInvoice(props.rows[i]);
  } else {
    selectedIndex.value = i;
  }
};

const scrollToSelected = () => {
  if (!tableRef.value || selectedIndex.value === -1) return;
  const row = tableRef.value.querySelector(`tr[data-index="${selectedIndex.value}"]`);
  row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

const goBack = () =>
  router.visit(route('reports.stock-item-summary', { productId: props.product.id }));

const openInvoice = (row) => {
  if (!row) return;
  if (row.vch_type === 'Purchase') {
    router.visit(route('purchase.show', { id: row.vch_no }));
  } else if (row.vch_type === 'Sales') {
    router.visit(route('sale.show', { id: row.vch_no }));
  }
};

const handleKeydown = (e) => {
  const len = props.rows.length;
  if (!len) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (selectedIndex.value === -1) {
      selectedIndex.value = 0;
    } else {
      selectedIndex.value = Math.min(selectedIndex.value + 1, len - 1);
    }
    nextTick(scrollToSelected);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    if (selectedIndex.value === -1) {
      selectedIndex.value = len - 1;
    } else {
      selectedIndex.value = Math.max(selectedIndex.value - 1, 0);
    }
    nextTick(scrollToSelected);
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (selectedIndex.value >= 0 && selectedIndex.value < len) {
      openInvoice(props.rows[selectedIndex.value]);
    }
  } else if (e.key === 'Escape') {
    goBack();
  }
};

onMounted(() => window.addEventListener('keydown', handleKeydown));
onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
</script>

<template>
  <Head :title="`Stock Ledger — ${product.name} (${monthName})`" />
  <AuthenticatedLayout>
    <div class="p-4 md:p-6 mx-auto space-y-0">

      <!-- ── Title bar ── -->
      <div class="flex items-center justify-between bg-[#2e2c92] text-white px-5 py-2 rounded-t-xl">
        <div class="flex items-center gap-3">
          <span class="text-sm font-bold tracking-wide">Stock Item</span>
          <span class="hidden items-center gap-1 text-white/50 text-[10px]">
            <kbd class="px-1 bg-white/10 rounded text-[9px] font-mono">↑</kbd>
            <kbd class="px-1 bg-white/10 rounded text-[9px] font-mono">↓</kbd>
            navigate &nbsp;·&nbsp;
            <kbd class="px-1.5 bg-white/10 rounded text-[9px] font-mono">Enter</kbd>
            / dbl-click open &nbsp;·&nbsp;
            <kbd class="px-1.5 bg-white/10 rounded text-[9px] font-mono">Esc</kbd>
            back
          </span>
        </div>
        <div>
          <button @click="goBack"
            class="text-white/70 hover:text-white transition text-lg leading-none flex items-center gap-1"
            title="Back (Esc)">
            <i class="bi bi-arrow-left"></i>
            <span class="text-sm font-semibold opacity-90">Back</span>
          </button>
        </div>
      </div>

      <!-- ── Sub-header: Tally-style top info bar ── -->
      <div class="bg-[#f0f0ff] border-x border-[#2e2c92]/20 px-5 py-2 flex items-center justify-between">
        <div>
          <p class="font-bold text-[#2e2c92] text-base tracking-wide">
            Stock Item: <span class="italic">{{ product.name }}</span>
          </p>
        </div>
        <div class="text-right text-sm font-semibold text-gray-700">
          {{ monthLabel }}
        </div>
      </div>

      <!-- ── Main Ledger Table ── -->
      <div class="overflow-x-auto border border-[#2e2c92]/20 rounded-b-xl shadow-sm">
        <table ref="tableRef" class="w-full text-sm border-collapse">

          <!-- Column Headers — Tally style -->
          <thead>
            <tr class="bg-white border-b border-gray-300 text-center text-xs font-bold text-gray-700">
              <th class="px-3 py-2 text-left border-r border-gray-200 w-24">Date</th>
              <th class="px-3 py-2 text-left border-r border-gray-200">Particulars</th>
              <th class="px-3 py-2 border-r border-gray-200 w-24">Vch Type</th>
              <th class="px-3 py-2 border-r border-gray-200 w-20">Vch No.</th>
              <th colspan="2" class="px-3 py-2 border-r border-gray-200 bg-blue-50/60">Inwards</th>
              <th colspan="2" class="px-3 py-2 border-r border-gray-200 bg-red-50/60">Outwards</th>
              <th colspan="2" class="px-3 py-2 bg-green-50/60">Closing</th>
            </tr>
            <tr class="bg-white border-b-2 border-[#2e2c92]/30 text-[11px] text-gray-500 text-center font-semibold">
              <th class="px-3 py-1 border-r border-gray-200"></th>
              <th class="px-3 py-1 border-r border-gray-200"></th>
              <th class="px-3 py-1 border-r border-gray-200"></th>
              <th class="px-3 py-1 border-r border-gray-200"></th>
              <th class="px-3 py-1 border-r border-gray-100 bg-blue-50/40">Quantity</th>
              <th class="px-3 py-1 border-r border-gray-200 bg-blue-50/40">Value</th>
              <th class="px-3 py-1 border-r border-gray-100 bg-red-50/40">Quantity</th>
              <th class="px-3 py-1 border-r border-gray-200 bg-red-50/40">Value</th>
              <th class="px-3 py-1 border-r border-gray-100 bg-green-50/40">Quantity</th>
              <th class="px-3 py-1 bg-green-50/40">Value</th>
            </tr>
          </thead>

          <tbody>
            <!-- Opening Balance row -->
            <tr class="border-b border-gray-200 bg-gray-50/80 font-semibold text-gray-800">
              <td class="px-3 py-2 text-xs border-r border-gray-200 whitespace-nowrap">
                {{ monthLabel.split(' to ')[0] }}
              </td>
              <td class="px-3 py-2 border-r border-gray-200 font-bold">Opening Balance</td>
              <td class="px-3 py-2 border-r border-gray-200"></td>
              <td class="px-3 py-2 border-r border-gray-200"></td>
              <!-- in -->
              <td class="px-3 py-2 text-right bg-blue-50/20 border-r border-gray-100 text-gray-400">—</td>
              <td class="px-3 py-2 text-right bg-blue-50/20 border-r border-gray-200 text-gray-400">—</td>
              <!-- out -->
              <td class="px-3 py-2 text-right bg-red-50/20 border-r border-gray-100 text-gray-400">—</td>
              <td class="px-3 py-2 text-right bg-red-50/20 border-r border-gray-200 text-gray-400">—</td>
              <!-- closing = opening -->
              <td class="px-3 py-2 text-right bg-green-50/30 border-r border-gray-100 font-bold text-gray-800">
                {{ qty(openingQty) }}
              </td>
              <td class="px-3 py-2 text-right bg-green-50/30 font-bold text-gray-800">
                {{ fmtNum(openingValue) }}
              </td>
            </tr>

            <!-- No transactions -->
            <tr v-if="rows.length === 0">
              <td colspan="10" class="px-5 py-8 text-center text-gray-400 italic text-sm">
                No transactions in {{ monthName }}
              </td>
            </tr>

            <!-- Transaction rows -->
            <tr
              v-for="(row, i) in rows"
              :key="i"
              :data-index="i"
              @click="selectRow(i)"
              @dblclick="openInvoice(row)"
              :class="[
                'border-b border-gray-100 cursor-pointer select-none transition-colors duration-100',
                selectedIndex === i
                  ? 'bg-[#f5c518] text-gray-900 font-semibold selected-row'
                  : i % 2 === 0
                    ? 'bg-white hover:bg-indigo-50/40 text-gray-800'
                    : 'bg-gray-50/40 hover:bg-indigo-50/40 text-gray-800'
              ]"
            >
              <!-- Date -->
              <td class="px-3 py-2 text-xs border-r border-gray-200 whitespace-nowrap"
                :class="selectedIndex === i ? 'text-gray-900' : 'text-gray-600'">
                {{ row.date }}
              </td>

              <!-- Particulars (party name) -->
              <td class="px-3 py-2 border-r border-gray-200 font-medium"
                :class="selectedIndex === i ? 'text-gray-900' : ''">
                {{ row.party }}
              </td>

              <!-- Vch Type -->
              <td class="px-3 py-2 border-r border-gray-200 text-center"
                :class="[
                  selectedIndex === i ? 'text-gray-900' : '',
                  row.vch_type === 'Purchase' ? 'text-blue-700 font-semibold' : 'text-orange-600 font-semibold'
                ]">
                {{ row.vch_type }}
              </td>

              <!-- Vch No. -->
              <td class="px-3 py-2 border-r border-gray-200 text-center text-gray-500">
                {{ row.vch_no }}
              </td>

              <!-- Inwards Qty -->
              <td class="px-3 py-2 text-right border-r border-gray-100"
                :class="selectedIndex === i ? 'text-blue-800' : 'bg-blue-50/20 text-blue-700'">
                {{ row.in_qty > 0 ? qty(row.in_qty) : '' }}
              </td>
              <!-- Inwards Val -->
              <td class="px-3 py-2 text-right border-r border-gray-200"
                :class="selectedIndex === i ? 'text-blue-800' : 'bg-blue-50/20 text-blue-700'">
                {{ row.in_value > 0 ? fmtNum(row.in_value) : '' }}
              </td>

              <!-- Outwards Qty -->
              <td class="px-3 py-2 text-right border-r border-gray-100"
                :class="selectedIndex === i ? 'text-red-800' : 'bg-red-50/20 text-red-700'">
                {{ row.out_qty > 0 ? qty(row.out_qty) : '' }}
              </td>
              <!-- Outwards Val -->
              <td class="px-3 py-2 text-right border-r border-gray-200"
                :class="selectedIndex === i ? 'text-red-800' : 'bg-red-50/20 text-red-700'">
                {{ row.out_value > 0 ? fmtNum(row.out_value) : '' }}
              </td>

              <!-- Closing Qty -->
              <td class="px-3 py-2 text-right font-semibold border-r border-gray-100"
                :class="selectedIndex === i ? 'text-gray-900' : 'bg-green-50/30 text-emerald-800'">
                {{ qty(row.closing_qty) }}
              </td>
              <!-- Closing Val -->
              <td class="px-3 py-2 text-right font-semibold"
                :class="selectedIndex === i ? 'text-gray-900' : 'bg-green-50/30 text-emerald-800'">
                {{ fmtNum(row.closing_value) }}
              </td>
            </tr>

          </tbody>

          <!-- Totals footer — "Totals as per 'Default' valuation" style -->
          <tfoot>
            <tr class="border-t-2 border-[#2e2c92]/40 bg-gray-100 font-bold text-gray-800 text-sm">
              <td colspan="4" class="px-3 py-3 text-left border-r border-gray-300 text-xs tracking-wide">
                Totals as per 'Default' valuation :
              </td>
              <td class="px-3 py-3 text-right bg-blue-100/50 border-r border-gray-200">
                {{ totalInQty > 0 ? qty(totalInQty) : '' }}
              </td>
              <td class="px-3 py-3 text-right bg-blue-100/50 border-r border-gray-300">
                {{ totalInVal > 0 ? fmtNum(totalInVal) : '' }}
              </td>
              <td class="px-3 py-3 text-right bg-red-100/50 border-r border-gray-200">
                {{ totalOutQty > 0 ? qty(totalOutQty) : '' }}
              </td>
              <td class="px-3 py-3 text-right bg-red-100/50 border-r border-gray-300">
                {{ totalOutVal > 0 ? fmtNum(totalOutVal) : '' }}
              </td>
              <td class="px-3 py-3 text-right bg-green-100/60 border-r border-gray-200">
                {{ qty(closingQty) }}
              </td>
              <td class="px-3 py-3 text-right bg-green-100/60">
                {{ fmtNum(closingVal) }}
              </td>
            </tr>
          </tfoot>

        </table>
      </div>

      <!-- ── Back button ── -->
      <div class="pt-3">
        <button @click="goBack"
          class="inline-flex items-center gap-2 text-sm text-[#2e2c92] hover:text-indigo-800 font-semibold transition">
          <i class="bi bi-arrow-left"></i> Back to Monthly Summary
        </button>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
table { border-spacing: 0; }
tbody tr { transition: background-color 0.1s ease, color 0.1s ease; }
tr.selected-row td:first-child { border-left: 3px solid #f5c518; }
</style>
