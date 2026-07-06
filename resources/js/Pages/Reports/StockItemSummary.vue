<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

const props = defineProps({
  product:         { type: Object, required: true },
  storeName:       { type: String, required: true },
  fyLabel:         { type: String, required: true },
  fyYear:          { type: String, required: true },
  openingQty:      { type: Number, required: true },
  openingValue:    { type: Number, required: true },
  rows:            { type: Array,  required: true },
  grandInQty:      { type: Number, required: true },
  grandInVal:      { type: Number, required: true },
  grandOutQty:     { type: Number, required: true },
  grandOutVal:     { type: Number, required: true },
  grandClosingQty: { type: Number, required: true },
  grandClosingVal: { type: Number, required: true },
});

// ── Formatters ──────────────────────────────────────────────────────────
const fmtNum = (n) =>
  new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n || 0);

const unit = props.product.unit_type || '';
const qty  = (n) => (n > 0 ? `${n}${unit ? ' ' + unit : ''}` : '—');

// ── Bar chart ───────────────────────────────────────────────────────────
const maxBar = computed(() => Math.max(...props.rows.flatMap(r => [r.in_qty, r.out_qty]), 1));
const barH   = (val) => Math.round((val / maxBar.value) * 80);

// ── Row selection & keyboard nav ────────────────────────────────────────
const selectedIndex = ref(0);   // first row selected on mount
const tableRef      = ref(null);

const selectRow = (index) => {
  selectedIndex.value = index;
};

const scrollToSelected = () => {
  if (!tableRef.value) return;
  const row = tableRef.value.querySelector(`tr[data-index="${selectedIndex.value}"]`);
  row?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

const handleKeydown = (e) => {
  const len = props.rows.length;
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
    const row = props.rows[selectedIndex.value];
    openMonthDetail(row);
  } else if (e.key === 'Escape') {
    goBack();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeydown);
  // auto-highlight current month if exists
  const currentIdx = props.rows.findIndex(r => r.is_current);
  if (currentIdx !== -1) selectedIndex.value = currentIdx;
  nextTick(scrollToSelected);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeydown);
});

// ── Navigation ──────────────────────────────────────────────────────────────
const goBack = () => router.visit(route('reports.stock-summary'));

const openMonthDetail = (row) => {
  if (!row) return;
  router.visit(route('reports.stock-item-month', {
    productId: props.product.id,
    year:  row.year,
    month: row.month,
  }));
};

// ── Selected row computed data (for info panel) ─────────────────────────
const selectedRow = computed(() => props.rows[selectedIndex.value] ?? null);
</script>

<template>
  <Head :title="`Stock Item Summary — ${product.name}`" />
  <AuthenticatedLayout>
    <div class="p-4 md:p-6 mx-auto space-y-0">

      <!-- ── Title bar ── -->
      <div class="flex items-center justify-between bg-[#2e2c92] text-white px-5 py-2 rounded-t-xl">
        <div class="flex items-center gap-3">
          <span class="text-sm font-bold tracking-wide">Stock Item Monthly Summary</span>
          <!-- keyboard hint -->
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
        <div class="flex items-center gap-4">
            <button @click="goBack"
                class="text-white/70 hover:text-white transition text-lg leading-none flex items-center gap-1"
                title="Close (Esc)"><i class="bi bi-arrow-left"></i>
                <span class="text-sm font-semibold opacity-90">Back</span>
            </button>

        </div>
      </div>

      <!-- ── Sub-header ── -->
      <div class="bg-[#f0f0ff] border border-[#2e2c92]/20 px-5 py-3 flex items-center justify-between gap-4 rounded-tl-xl rounded-tr-xl">
        <div><p class="font-bold text-[#2e2c92] text-base tracking-wide italic">{{ product.name }}</p></div>
        <div class="flex flex-col items-end">
            <p class="text-sm text-gray-600 font-semibold">{{ storeName }}</p>
            <p class="text-xs text-gray-500 mt-0.5">For {{ fyLabel }}</p>
        </div>
      </div>

      <!-- ── Selected month info strip ── -->
      <transition name="slide-down">
        <div v-if="selectedRow"
          class="flex items-center justify-between bg-[#2e2c92]/5 border-x border-[#2e2c92]/20 px-5 py-2 text-xs text-gray-600 font-medium">
          <span class="font-bold text-[#2e2c92]">{{ selectedRow.label }}</span>
          <div class="flex gap-6">
            <span>📥 Inwards:
              <strong>{{ selectedRow.in_qty > 0 ? qty(selectedRow.in_qty) : '—' }}</strong>
              &nbsp;
              <span class="text-gray-400">{{ selectedRow.in_value > 0 ? fmtNum(selectedRow.in_value) : '' }}</span>
            </span>
            <span>📤 Outwards:
              <strong>{{ selectedRow.out_qty > 0 ? qty(selectedRow.out_qty) : '—' }}</strong>
              &nbsp;
              <span class="text-gray-400">{{ selectedRow.out_value > 0 ? fmtNum(selectedRow.out_value) : '' }}</span>
            </span>
            <span>📦 Closing:
              <strong class="text-emerald-700">{{ qty(selectedRow.closing_qty) }}</strong>
              &nbsp;
              <span class="text-gray-400">{{ fmtNum(selectedRow.closing_value) }}</span>
            </span>
          </div>
        </div>
      </transition>

      <!-- ── Main Table ── -->
      <div class="overflow-x-auto border border-[#2e2c92]/20 rounded-b-xl shadow-sm">
        <table ref="tableRef" class="w-full text-sm border-collapse">

          <!-- Column headers -->
          <thead>
            <tr class="bg-white border-b border-gray-300">
              <th class="px-4 py-2 text-left font-bold text-gray-700 w-40 border-r border-gray-200">Particulars</th>
              <th colspan="2" class="px-4 py-2 text-center font-bold text-gray-700 border-r border-gray-200 bg-blue-50/60">Inwards</th>
              <th colspan="2" class="px-4 py-2 text-center font-bold text-gray-700 border-r border-gray-200 bg-red-50/60">Outwards</th>
              <th colspan="2" class="px-4 py-2 text-center font-bold text-gray-700 bg-green-50/60">Closing Balance</th>
            </tr>
            <tr class="bg-white border-b-2 border-[#2e2c92]/30 text-xs text-gray-500">
              <th class="px-4 py-1.5 text-left border-r border-gray-200"></th>
              <th class="px-4 py-1.5 text-right font-semibold border-r border-gray-100 bg-blue-50/40">Quantity</th>
              <th class="px-4 py-1.5 text-right font-semibold border-r border-gray-200 bg-blue-50/40">Value</th>
              <th class="px-4 py-1.5 text-right font-semibold border-r border-gray-100 bg-red-50/40">Quantity</th>
              <th class="px-4 py-1.5 text-right font-semibold border-r border-gray-200 bg-red-50/40">Value</th>
              <th class="px-4 py-1.5 text-right font-semibold border-r border-gray-100 bg-green-50/40">Quantity</th>
              <th class="px-4 py-1.5 text-right font-semibold bg-green-50/40">Value</th>
            </tr>
          </thead>

          <tbody>

            <!-- Opening Balance row (non-selectable) -->
            <tr class="border-b border-gray-100 bg-gray-50/70">
              <td class="px-4 py-2 italic text-gray-500 text-xs border-r border-gray-200">Opening Balance</td>
              <td class="px-4 py-2 text-right bg-blue-50/20 text-gray-400 border-r border-gray-100">—</td>
              <td class="px-4 py-2 text-right bg-blue-50/20 text-gray-400 border-r border-gray-200">—</td>
              <td class="px-4 py-2 text-right bg-red-50/20 text-gray-400 border-r border-gray-100">—</td>
              <td class="px-4 py-2 text-right bg-red-50/20 text-gray-400 border-r border-gray-200">—</td>
              <td class="px-4 py-2 text-right bg-green-50/30 font-semibold text-gray-700 border-r border-gray-100">{{ qty(openingQty) }}</td>
              <td class="px-4 py-2 text-right bg-green-50/30 font-semibold text-gray-700">{{ fmtNum(openingValue) }}</td>
            </tr>

            <!-- Monthly rows -->
            <tr
              v-for="(row, i) in rows"
              :key="i"
              :data-index="i"
              @click="selectRow(i)"
              @dblclick="openMonthDetail(row)"
              :class="[
                'border-b border-gray-100 cursor-pointer select-none transition-colors duration-100',
                selectedIndex === i
                  ? 'bg-[#2e2c92] text-white selected-row'
                  : row.is_current
                    ? 'bg-amber-400/90 font-semibold text-gray-900 hover:bg-amber-400'
                    : i % 2 === 0
                      ? 'bg-white hover:bg-indigo-50/50 text-gray-800'
                      : 'bg-gray-50/40 hover:bg-indigo-50/50 text-gray-800'
              ]"
            >
              <!-- Month label -->
              <td class="px-4 py-2 border-r border-gray-200 font-medium"
                :class="selectedIndex === i ? 'border-r-indigo-400/30 text-white' : ''">
                {{ row.label }}
              </td>

              <!-- Inwards Qty -->
              <td class="px-4 py-2 text-right border-r border-gray-100"
                :class="selectedIndex === i ? 'text-blue-200' : 'bg-blue-50/20'">
                {{ row.in_qty > 0 ? qty(row.in_qty) : '' }}
              </td>
              <!-- Inwards Val -->
              <td class="px-4 py-2 text-right border-r border-gray-200"
                :class="selectedIndex === i ? 'text-blue-200' : 'bg-blue-50/20'">
                {{ row.in_value > 0 ? fmtNum(row.in_value) : '' }}
              </td>

              <!-- Outwards Qty -->
              <td class="px-4 py-2 text-right border-r border-gray-100"
                :class="selectedIndex === i ? 'text-red-200' : 'bg-red-50/20'">
                {{ row.out_qty > 0 ? qty(row.out_qty) : '' }}
              </td>
              <!-- Outwards Val -->
              <td class="px-4 py-2 text-right border-r border-gray-200"
                :class="selectedIndex === i ? 'text-red-200' : 'bg-red-50/20'">
                {{ row.out_value > 0 ? fmtNum(row.out_value) : '' }}
              </td>

              <!-- Closing Qty -->
              <td class="px-4 py-2 text-right font-semibold border-r border-gray-100"
                :class="selectedIndex === i ? 'text-emerald-200' : 'bg-green-50/30 text-emerald-800'">
                {{ qty(row.closing_qty) }}
              </td>
              <!-- Closing Val -->
              <td class="px-4 py-2 text-right font-semibold"
                :class="selectedIndex === i ? 'text-emerald-200' : 'bg-green-50/30 text-emerald-800'">
                {{ fmtNum(row.closing_value) }}
              </td>
            </tr>

          </tbody>

          <!-- Grand Total footer -->
          <tfoot>
            <tr class="border-t-2 border-[#2e2c92]/40 bg-gray-100 font-bold text-gray-800">
              <td class="px-4 py-3 tracking-wider uppercase text-xs border-r border-gray-300">Grand Total</td>
              <td class="px-4 py-3 text-right bg-blue-100/50 border-r border-gray-200">{{ qty(grandInQty) }}</td>
              <td class="px-4 py-3 text-right bg-blue-100/50 border-r border-gray-300">{{ fmtNum(grandInVal) }}</td>
              <td class="px-4 py-3 text-right bg-red-100/50 border-r border-gray-200">{{ qty(grandOutQty) }}</td>
              <td class="px-4 py-3 text-right bg-red-100/50 border-r border-gray-300">{{ fmtNum(grandOutVal) }}</td>
              <td class="px-4 py-3 text-right bg-green-100/60 border-r border-gray-200">{{ qty(grandClosingQty) }}</td>
              <td class="px-4 py-3 text-right bg-green-100/60">{{ fmtNum(grandClosingVal) }}</td>
            </tr>
          </tfoot>

        </table>
      </div>

      <!-- ── Bar Chart ── -->
      <div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm p-5">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">
          Monthly Movement Chart
          <span class="ml-4 font-normal text-gray-400">
            <span class="inline-block w-3 h-3 bg-red-500 rounded-sm mr-1"></span> Inwards &nbsp;
            <span class="inline-block w-3 h-3 bg-blue-500 rounded-sm mr-1"></span> Outwards
          </span>
        </p>
        <div class="flex items-end gap-1.5 h-28 overflow-x-auto pb-1">
          <div
            v-for="(row, i) in rows"
            :key="i"
            class="flex flex-col items-center gap-0.5 flex-shrink-0 cursor-pointer"
            style="min-width: 52px;"
            @click="selectRow(i)"
            :class="selectedIndex === i ? 'opacity-100' : 'opacity-70 hover:opacity-100'"
          >
            <div class="flex items-end gap-0.5" style="height: 88px;">
              <div
                class="w-5 rounded-t transition-all duration-300"
                :class="[
                  row.in_qty > 0 ? 'bg-red-500' : 'bg-gray-200',
                  selectedIndex === i ? 'ring-2 ring-red-300' : ''
                ]"
                :style="`height: ${row.in_qty > 0 ? Math.max(barH(row.in_qty), 3) : 2}px`"
                :title="`Inwards: ${row.in_qty}`"
              ></div>
              <div
                class="w-5 rounded-t transition-all duration-300"
                :class="[
                  row.out_qty > 0 ? 'bg-blue-500' : 'bg-gray-200',
                  selectedIndex === i ? 'ring-2 ring-blue-300' : ''
                ]"
                :style="`height: ${row.out_qty > 0 ? Math.max(barH(row.out_qty), 3) : 2}px`"
                :title="`Outwards: ${row.out_qty}`"
              ></div>
            </div>
            <span class="text-[10px] font-medium transition-colors"
              :class="selectedIndex === i ? 'text-[#2e2c92] font-bold' : 'text-gray-400'">
              {{ row.label.slice(0, 3) }}
            </span>
          </div>
        </div>
      </div>

      <!-- ── Back button ── -->
      <div class="pt-2">
        <button @click="goBack"
          class="inline-flex items-center gap-2 text-sm text-[#2e2c92] hover:text-indigo-800 font-semibold transition">
          <i class="bi bi-arrow-left"></i> Back to Stock Summary
        </button>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
table { border-spacing: 0; }
tbody tr { transition: background-color 0.1s ease, color 0.1s ease; }
tr.selected-row td:first-child { border-left: 3px solid #a5b4fc; }

.slide-down-enter-active, .slide-down-leave-active {
  transition: all 0.2s ease;
}
.slide-down-enter-from, .slide-down-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
