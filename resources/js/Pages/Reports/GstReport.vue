<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import "vue3-toastify/dist/index.css";

const props = defineProps({
  salesReport: {
    type: Array,
    required: true
  },
  purchasesReport: {
    type: Array,
    required: true
  },
  summary: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({ start_date: '', end_date: '' })
  }
});

// Create local reactive refs for data to support instant UI updates
const salesData = ref([...props.salesReport]);
const purchasesData = ref([...props.purchasesReport]);

// Local search and tab states
const activeTab = ref('sales');
const salesSearch = ref('');
const purchasesSearch = ref('');
const filterStartDate = ref(props.filters.start_date || '');
const filterEndDate = ref(props.filters.end_date || '');

// Format currency helper
const formatCurrency = (val) => {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR',
    minimumFractionDigits: 2
  }).format(val || 0);
};

// Filtered data computed properties
const filteredSales = computed(() => {
  if (!salesSearch.value.trim()) return salesData.value;
  const q = salesSearch.value.toLowerCase();
  return salesData.value.filter(s =>
    s.invoice_no.toLowerCase().includes(q) ||
    s.customer_name.toLowerCase().includes(q) ||
    (s.gstin && s.gstin.toLowerCase().includes(q))
  );
});

const filteredPurchases = computed(() => {
  if (!purchasesSearch.value.trim()) return purchasesData.value;
  const q = purchasesSearch.value.toLowerCase();
  return purchasesData.value.filter(p =>
    p.invoice_no.toLowerCase().includes(q) ||
    p.supplier_name.toLowerCase().includes(q) ||
    (p.gstin && p.gstin.toLowerCase().includes(q))
  );
});

// Dynamic summaries calculated directly from local reactive state
const dynamicSummary = computed(() => {
  let totalSalesTaxable = 0;
  let totalOutputCgst = 0;
  let totalOutputSgst = 0;
  let totalOutputIgst = 0;
  let totalOutputGst = 0;

  salesData.value.forEach(s => {
    totalSalesTaxable += parseFloat(s.taxable_amount) || 0;
    totalOutputCgst += parseFloat(s.cgst) || 0;
    totalOutputSgst += parseFloat(s.sgst) || 0;
    totalOutputIgst += parseFloat(s.igst) || 0;
    totalOutputGst += parseFloat(s.total_gst) || 0;
  });

  let totalPurchasesTaxable = 0;
  let totalInputCgst = 0;
  let totalInputSgst = 0;
  let totalInputIgst = 0;
  let totalInputGst = 0;
  let refundableInputGst = 0;
  let nonRefundableInputGst = 0;

  purchasesData.value.forEach(p => {
    totalPurchasesTaxable += parseFloat(p.taxable_amount) || 0;
    totalInputCgst += parseFloat(p.cgst) || 0;
    totalInputSgst += parseFloat(p.sgst) || 0;
    totalInputIgst += parseFloat(p.igst) || 0;

    const itemGst = parseFloat(p.total_gst) || 0;
    totalInputGst += itemGst;

    if (p.is_refundable) {
      refundableInputGst += itemGst;
    } else {
      nonRefundableInputGst += itemGst;
    }
  });

  const netTaxAmount = totalOutputGst - refundableInputGst;

  return {
    total_sales_taxable: totalSalesTaxable,
    total_output_cgst: totalOutputCgst,
    total_output_sgst: totalOutputSgst,
    total_output_igst: totalOutputIgst,
    total_output_gst: totalOutputGst,

    total_purchases_taxable: totalPurchasesTaxable,
    total_input_cgst: totalInputCgst,
    total_input_sgst: totalInputSgst,
    total_input_igst: totalInputIgst,
    total_input_gst: totalInputGst,

    refundable_input_gst: refundableInputGst,
    non_refundable_input_gst: nonRefundableInputGst,

    net_tax_amount: Math.abs(netTaxAmount),
    net_status: netTaxAmount >= 0 ? 'Payable' : 'Receivable'
  };
});

// Toggle Refundable Status API call
const isToggling = ref({});
const toggleRefundableStatus = async (purchase) => {
  if (isToggling.value[purchase.id]) return;

  isToggling.value[purchase.id] = true;
  try {
    const response = await axios.post(`/purchases/${purchase.id}/toggle-refundable`);

    // Update local state instantly
    purchase.is_refundable = response.data.is_refundable;

    toast.success(
      purchase.is_refundable
        ? "GST marked as Refundable (Claimable ITC)!"
        : "GST marked as Non-Refundable (Blocked Credit)!"
    );
  } catch (error) {
    console.error("Error toggling refundable status:", error);
    toast.error("Failed to update status. Please try again.");
  } finally {
    isToggling.value[purchase.id] = false;
  }
};

// Apply date range filters
const applyFilters = () => {
  router.get(route('reports.gst'), {
    start_date: filterStartDate.value,
    end_date: filterEndDate.value
  }, { preserveState: true });
};

// Clear date range filters
const clearFilters = () => {
  filterStartDate.value = '';
  filterEndDate.value = '';
  router.get(route('reports.gst'), {}, { preserveState: true });
};

// Print page handler
const printReport = () => {
  window.print();
};
</script>

<template>
  <Head title="GST Compliance Report" />

  <AuthenticatedLayout>
    <div class="p-6 max-w-7xl mx-auto space-y-8 print:p-0 print:max-w-full">

      <!-- Top header with clean design -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 no-print">
        <div>
          <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight flex items-center gap-2">
            <span>GST Compliance & Summary Report</span>
            <span class="text-xs bg-[#2e2c92] text-white px-2.5 py-1 rounded-full font-bold">Auto-Calculated</span>
          </h1>
          <p class="text-gray-500 mt-1">Verify sales output tax, purchases input credit (ITC) and track net tax liabilities.</p>
        </div>

        <div class="flex items-center gap-3">
          <button
            @click="printReport"
            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold transition flex items-center gap-2 border border-gray-200"
          >
            <i class="bi bi-printer"></i> Print / PDF
          </button>

          <div class="bg-gray-100 p-1 rounded-xl flex items-center shadow-inner">
            <button
              @click="activeTab = 'sales'"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                activeTab === 'sales'
                  ? 'bg-white text-[#2e2c92] shadow'
                  : 'text-gray-600 hover:text-gray-900'
              ]"
            >
              📈 Sales (Output GST)
            </button>
            <button
              @click="activeTab = 'purchases'"
              :class="[
                'px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                activeTab === 'purchases'
                  ? 'bg-white text-[#2e2c92] shadow'
                  : 'text-gray-600 hover:text-gray-900'
              ]"
            >
              📉 Purchases (Input ITC)
            </button>
          </div>
        </div>
      </div>

      <!-- Date filters bar -->
      <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-md flex flex-wrap items-end gap-4 no-print">
        <div class="w-full sm:w-auto">
          <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase tracking-wider">Start Date</label>
          <input
            type="date"
            v-model="filterStartDate"
            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#2e2c92] focus:outline-none"
          />
        </div>
        <div class="w-full sm:w-auto">
          <label class="block text-xs font-bold text-gray-500 mb-1.5 uppercase tracking-wider">End Date</label>
          <input
            type="date"
            v-model="filterEndDate"
            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#2e2c92] focus:outline-none"
          />
        </div>
        <div class="flex gap-2 w-full sm:w-auto">
          <button
            @click="applyFilters"
            class="flex-1 sm:flex-initial px-5 py-2 bg-[#2e2c92] hover:bg-[#3d3bb3] text-white rounded-xl text-sm font-semibold transition"
          >
            Apply Filters
          </button>
          <button
            @click="clearFilters"
            class="flex-1 sm:flex-initial px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-semibold transition border border-gray-200"
          >
            Clear
          </button>
        </div>
      </div>

      <!-- High-level Summary Metrics Cards -->
      <div class="gst-summary-grid no-print">
        <!-- Card 1: Output GST -->
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-md flex flex-col justify-between h-32 border-l-4 border-l-indigo-600">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Sales Output GST</span>
          <span class="text-2xl font-extrabold text-gray-800">{{ formatCurrency(dynamicSummary.total_output_gst) }}</span>
          <span class="text-[10px] text-gray-400 font-medium">Taxable: {{ formatCurrency(dynamicSummary.total_sales_taxable) }}</span>
        </div>

        <!-- Card 2: Total Input GST -->
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-md flex flex-col justify-between h-32 border-l-4 border-l-gray-400">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Purchase GST</span>
          <span class="text-2xl font-extrabold text-gray-700">{{ formatCurrency(dynamicSummary.total_input_gst) }}</span>
          <span class="text-[10px] text-gray-400 font-medium">Taxable: {{ formatCurrency(dynamicSummary.total_purchases_taxable) }}</span>
        </div>

        <!-- Card 3: Refundable ITC -->
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-md flex flex-col justify-between h-32 border-l-4 border-l-emerald-500">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Refundable GST (ITC)</span>
          <span class="text-2xl font-extrabold text-emerald-600">{{ formatCurrency(dynamicSummary.refundable_input_gst) }}</span>
          <span class="text-[10px] text-emerald-600/80 font-medium">Claimable GST</span>
        </div>

        <!-- Card 4: Non-Refundable ITC -->
        <div class="bg-white border border-gray-100 p-5 rounded-2xl shadow-md flex flex-col justify-between h-32 border-l-4 border-l-rose-500">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Non-Refundable GST</span>
          <span class="text-2xl font-extrabold text-rose-600">{{ formatCurrency(dynamicSummary.non_refundable_input_gst) }}</span>
          <span class="text-[10px] text-rose-600/80 font-medium">Blocked Credit (Expenses)</span>
        </div>

        <!-- Card 5: Net Government Balance -->
        <div
          :class="[
            'p-5 rounded-2xl shadow-lg flex flex-col justify-between h-32 border transition-all duration-300',
            dynamicSummary.net_status === 'Payable'
              ? 'bg-amber-50 border-amber-200 text-amber-900 shadow-amber-100/50'
              : 'bg-emerald-50 border-emerald-200 text-emerald-900 shadow-emerald-100/50'
          ]"
        >
          <span class="text-xs font-bold uppercase tracking-wider opacity-85">
            {{ dynamicSummary.net_status === 'Payable' ? 'Net Payable ⚠️' : 'Net Refundable 🎉' }}
          </span>
          <span class="text-2xl font-black">
            {{ formatCurrency(dynamicSummary.net_tax_amount) }}
          </span>
          <span class="text-[10px] font-bold opacity-80 uppercase tracking-wide">
            {{ dynamicSummary.net_status === 'Payable' ? 'Pay' : 'Receive' }}
          </span>
        </div>
      </div>

      <!-- Main Tables Display Container -->
      <div class="space-y-6">

        <!-- Sales GST Table -->
        <div v-if="activeTab === 'sales'" class="print-area bg-white p-6 rounded-2xl border border-gray-100 shadow-md animate-fadeIn">
          <!-- Print-only Header for Sales -->
          <div class="print-header hidden print:block">
            <div class="print-header-top">
              <div>
                <div class="print-company">OkayERP</div>
                <div class="print-report-name">GST Compliance Report — Sales (Output Tax)</div>
              </div>
              <div class="print-header-meta">
                <div>Report Type: <strong>Sales GST</strong></div>
                <div>Printed: <strong>{{ new Date().toLocaleDateString('en-IN') }}</strong></div>
              </div>
            </div>
            <div class="print-header-divider"></div>
          </div>

          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 no-print">
            <div>
              <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span>Sales GST Records (Output Tax)</span>
                <span class="text-[10px] bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded font-bold uppercase">Receivables Summary</span>
              </h2>
              <p class="text-xs text-gray-400 mt-0.5">GST collected from customers on sales invoices.</p>
            </div>

            <input
              type="text"
              v-model="salesSearch"
              placeholder="Search by invoice, customer, GSTIN..."
              class="w-full sm:w-72 px-3 py-1.5 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#2e2c92] focus:outline-none"
            />
          </div>

          <div class="overflow-x-auto table-wrapper">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="bg-[#2e2c92] text-white text-xs uppercase tracking-wider text-left">
                  <th class="p-3 font-semibold rounded-l-lg text-center">S.N.</th>
                  <th class="p-3 font-semibold">Invoice No</th>
                  <th class="p-3 font-semibold">Date</th>
                  <th class="p-3 font-semibold">Customer</th>
                  <th class="p-3 font-semibold">GSTIN</th>
                  <th class="p-3 font-semibold text-right">Taxable Amt</th>
                  <th class="p-3 font-semibold text-right">CGST</th>
                  <th class="p-3 font-semibold text-right">SGST</th>
                  <th class="p-3 font-semibold text-right">IGST</th>
                  <th class="p-3 font-semibold text-right rounded-r-lg">Total Tax</th>
                </tr>
              </thead>
              <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                <tr v-if="filteredSales.length === 0">
                  <td colspan="10" class="p-6 text-center text-gray-400 font-medium bg-gray-50 rounded-lg">
                    No sales GST records found matching your filters.
                  </td>
                </tr>
                <tr
                  v-for="(row, idx) in filteredSales"
                  :key="row.id"
                  :class="[
                    'transition',
                    row.is_return ? 'bg-amber-50/20 hover:bg-amber-50/40 text-amber-900' : 'hover:bg-gray-50/50'
                  ]"
                >
                  <td class="p-3 text-center border-b border-gray-100 font-medium text-gray-400">{{ idx + 1 }}</td>
                  <td class="p-3 border-b border-gray-100 font-semibold text-gray-900">
                    <div class="flex items-center gap-1.5 justify-start">
                      <span>{{ row.invoice_no }}</span>
                      <span v-if="row.is_return" class="px-1.5 py-0.5 text-[9px] font-bold bg-amber-100 text-amber-800 rounded uppercase">Return</span>
                    </div>
                  </td>
                  <td class="p-3 border-b border-gray-100 whitespace-nowrap">{{ row.date }}</td>
                  <td class="p-3 border-b border-gray-100 font-medium">{{ row.customer_name }}</td>
                  <td class="p-3 border-b border-gray-100 whitespace-nowrap font-mono text-gray-500">{{ row.gstin }}</td>
                  <td class="p-3 border-b border-gray-100 text-right font-medium">{{ formatCurrency(row.taxable_amount) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right text-gray-500">{{ formatCurrency(row.cgst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right text-gray-500">{{ formatCurrency(row.sgst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right text-gray-500">{{ formatCurrency(row.igst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right font-bold text-gray-900">{{ formatCurrency(row.total_gst) }}</td>
                </tr>
              </tbody>
              <tfoot v-if="filteredSales.length > 0" class="bg-gray-50/70 border-t-2 border-gray-200">
                <tr class="font-bold text-gray-900 text-right text-xs">
                  <td colspan="5" class="p-3 text-left">Total Sales Summary</td>
                  <td class="p-3">{{ formatCurrency(dynamicSummary.total_sales_taxable) }}</td>
                  <td class="p-3 text-gray-600">{{ formatCurrency(dynamicSummary.total_output_cgst) }}</td>
                  <td class="p-3 text-gray-600">{{ formatCurrency(dynamicSummary.total_output_sgst) }}</td>
                  <td class="p-3 text-gray-600">{{ formatCurrency(dynamicSummary.total_output_igst) }}</td>
                  <td class="p-3 text-[#2e2c92] font-extrabold">{{ formatCurrency(dynamicSummary.total_output_gst) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Purchases GST Table -->
        <div v-else class="print-area bg-white p-6 rounded-2xl border border-gray-100 shadow-md animate-fadeIn">
          <!-- Print-only Header for Purchases -->
          <div class="print-header hidden print:block">
            <div class="print-header-top">
              <div>
                <div class="print-company">OkayERP</div>
                <div class="print-report-name">GST Compliance Report — Purchases (Input ITC)</div>
              </div>
              <div class="print-header-meta">
                <div>Report Type: <strong>Purchase GST / ITC</strong></div>
                <div>Printed: <strong>{{ new Date().toLocaleDateString('en-IN') }}</strong></div>
              </div>
            </div>
            <div class="print-header-divider"></div>
          </div>

          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 no-print">
            <div>
              <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <span>Purchases GST Records (Input ITC)</span>
                <span class="text-[10px] bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded font-bold uppercase">Credit Claim Tracker</span>
              </h2>
              <p class="text-xs text-gray-400 mt-0.5">GST paid to suppliers on purchases. Manage Blocked Credit (Non-Refundable) vs Claimable ITC.</p>
            </div>

            <input
              type="text"
              v-model="purchasesSearch"
              placeholder="Search by purchase invoice, supplier, GSTIN..."
              class="w-full sm:w-72 px-3 py-1.5 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#2e2c92] focus:outline-none"
            />
          </div>

          <div class="overflow-x-auto table-wrapper">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="bg-gray-800 text-white text-xs uppercase tracking-wider text-left">
                  <th class="p-3 font-semibold rounded-l-lg text-center">S.N.</th>
                  <th class="p-3 font-semibold">Purchase No</th>
                  <th class="p-3 font-semibold">Date</th>
                  <th class="p-3 font-semibold">Supplier</th>
                  <th class="p-3 font-semibold">GSTIN</th>
                  <th class="p-3 font-semibold text-right">Taxable Amt</th>
                  <th class="p-3 font-semibold text-right">CGST</th>
                  <th class="p-3 font-semibold text-right">SGST</th>
                  <th class="p-3 font-semibold text-right">IGST</th>
                  <th class="p-3 font-semibold text-right">Total Tax</th>
                  <th class="p-3 font-semibold text-center rounded-r-lg print:hidden">Refundable Status</th>
                </tr>
              </thead>
              <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                <tr v-if="filteredPurchases.length === 0">
                  <td colspan="11" class="p-6 text-center text-gray-400 font-medium bg-gray-50 rounded-lg">
                    No purchase GST records found matching your filters.
                  </td>
                </tr>
                <tr
                  v-for="(row, idx) in filteredPurchases"
                  :key="row.id"
                  :class="[
                    'transition',
                    row.is_return ? 'bg-amber-50/20 hover:bg-amber-50/40 text-amber-900' : (row.is_refundable ? 'hover:bg-gray-50/50' : 'bg-rose-50/20 hover:bg-rose-50/40')
                  ]"
                >
                  <td class="p-3 text-center border-b border-gray-100 font-medium text-gray-400">{{ idx + 1 }}</td>
                  <td class="p-3 border-b border-gray-100 font-semibold text-gray-900">
                    <div class="flex items-center gap-1.5 justify-start">
                      <span>{{ row.invoice_no }}</span>
                      <span v-if="row.is_return" class="px-1.5 py-0.5 text-[9px] font-bold bg-amber-100 text-amber-800 rounded uppercase">Return</span>
                    </div>
                  </td>
                  <td class="p-3 border-b border-gray-100 whitespace-nowrap">{{ row.date }}</td>
                  <td class="p-3 border-b border-gray-100 font-medium">{{ row.supplier_name }}</td>
                  <td class="p-3 border-b border-gray-100 whitespace-nowrap font-mono text-gray-500">{{ row.gstin }}</td>
                  <td class="p-3 border-b border-gray-100 text-right font-medium">{{ formatCurrency(row.taxable_amount) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right text-gray-500">{{ formatCurrency(row.cgst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right text-gray-500">{{ formatCurrency(row.sgst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right text-gray-500">{{ formatCurrency(row.igst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-right font-bold text-gray-900">{{ formatCurrency(row.total_gst) }}</td>
                  <td class="p-3 border-b border-gray-100 text-center print:hidden">
                    <template v-if="row.is_return">
                      <span
                        :class="[
                          'px-3 py-1 rounded-full text-[10px] font-bold uppercase inline-flex items-center gap-1.5 border mx-auto',
                          row.is_refundable
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                            : 'bg-rose-50 text-rose-700 border-rose-200'
                        ]"
                      >
                        <span
                          class="w-1.5 h-1.5 rounded-full"
                          :class="row.is_refundable ? 'bg-emerald-500' : 'bg-rose-500'"
                        ></span>
                        {{ row.is_refundable ? 'Refundable (ITC)' : 'Non-Refundable' }}
                      </span>
                    </template>
                    <button
                      v-else
                      @click="toggleRefundableStatus(row)"
                      :disabled="isToggling[row.id]"
                      :class="[
                        'px-3 py-1 rounded-full text-[10px] font-bold uppercase transition flex items-center gap-1.5 mx-auto border focus:outline-none',
                        row.is_refundable
                          ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100/50'
                          : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100/50'
                      ]"
                    >
                      <span
                        class="w-1.5 h-1.5 rounded-full"
                        :class="row.is_refundable ? 'bg-emerald-500' : 'bg-rose-500'"
                      ></span>
                      {{ row.is_refundable ? 'Refundable (ITC)' : 'Non-Refundable' }}
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="filteredPurchases.length > 0" class="bg-gray-50/70 border-t-2 border-gray-200">
                <tr class="font-bold text-gray-900 text-right text-xs">
                  <td colspan="5" class="p-3 text-left">Total Purchase Summary</td>
                  <td class="p-3">{{ formatCurrency(dynamicSummary.total_purchases_taxable) }}</td>
                  <td class="p-3 text-gray-600">{{ formatCurrency(dynamicSummary.total_input_cgst) }}</td>
                  <td class="p-3 text-gray-600">{{ formatCurrency(dynamicSummary.total_input_sgst) }}</td>
                  <td class="p-3 text-gray-600">{{ formatCurrency(dynamicSummary.total_input_igst) }}</td>
                  <td class="p-3 text-gray-800 font-extrabold">{{ formatCurrency(dynamicSummary.total_input_gst) }}</td>
                  <td class="p-3 border-b border-gray-100 print:hidden">&nbsp;</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.gst-summary-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 1.25rem;
}

@media (max-width: 1024px) {
  .gst-summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .gst-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .gst-summary-grid {
    grid-template-columns: 1fr;
  }
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.4s ease-out forwards;
}
</style>

<!-- Global (non-scoped) print styles -->
<style>
@page {
  size: A4 landscape;
  margin: 10mm;
}

@media print {
  /* 1. Remove browser header/footer (URL, date, page count) */
  /* This is handled by @page margin above */

  /* 2. Hide everything on page */
  body > * {
    display: none !important;
  }

  /* 3. Show only the Inertia app root */
  body > #app {
    display: block !important;
  }

  /* 4. Hide sidebar, top nav, all layout chrome */
  nav,
  aside,
  header,
  footer,
  [class*="sidebar"],
  [class*="Sidebar"],
  .no-print {
    display: none !important;
  }

  /* 5. Reset main layout wrappers */
  #app,
  #app > div,
  #app > div > div {
    display: block !important;
    width: 100% !important;
    height: auto !important;
    overflow: visible !important;
    background: white !important;
    padding: 0 !important;
    margin: 0 !important;
  }

  /* 6. Hide everything inside page except .print-area */
  .print-area {
    display: block !important;
    width: 100% !important;
    box-shadow: none !important;
    border: none !important;
    border-radius: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    background: white !important;
  }

  /* Hide all sibling divs that are NOT .print-area */
  .print-area ~ *,
  * ~ .print-area,
  .print-area + * {
    display: none !important;
  }

  /* 7. Table styles */
  .table-wrapper {
    overflow: visible !important;
    width: 100% !important;
  }

  table {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 10.5px !important;
  }

  th {
    padding: 7px 8px !important;
    border: 1px solid #1e1b4b !important;
    background-color: #1e1b4b !important;
    color: white !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    white-space: nowrap !important;
  }

  td {
    padding: 6px 8px !important;
    border: 1px solid #d1d5db !important;
    white-space: normal !important;
  }

  tfoot tr td {
    background-color: #f9fafb !important;
    font-weight: bold !important;
    border-top: 2px solid #374151 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  /* 8. Print header styles */
  .print-header {
    display: block !important;
    margin-bottom: 12px;
  }

  .print-header-top {
    display: flex !important;
    justify-content: space-between;
    align-items: flex-start;
  }

  .print-company {
    font-size: 20px;
    font-weight: 900;
    color: #1e1b4b;
    letter-spacing: -0.5px;
  }

  .print-report-name {
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    margin-top: 2px;
  }

  .print-header-meta {
    font-size: 10px;
    color: #6b7280;
    text-align: right;
    line-height: 1.6;
  }

  .print-header-meta strong {
    color: #111827;
  }

  .print-header-divider {
    height: 2px;
    background: linear-gradient(to right, #1e1b4b, #6366f1, transparent);
    margin: 8px 0 14px;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }

  /* 9. Hide search inputs, buttons, toggle buttons in print */
  input[type="text"],
  input[type="date"],
  button:not([data-print-keep]),
  .print\:hidden {
    display: none !important;
  }
}
</style>
