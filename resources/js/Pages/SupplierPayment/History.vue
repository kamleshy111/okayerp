<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  supplier: {
    type: Object,
    required: true
  },
  history: {
    type: Array,
    required: true
  },
  productHistory: {
    type: Array,
    default: () => []
  },
  purchasedProducts: {
    type: Array,
    default: () => []
  }
});

// Active Tab: 'invoice' (Ledger) vs 'product' (Product-wise Report)
const activeTab = ref('invoice');

// Date formatter helper
const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

// Calculate summary stats for Ledger
const totalDebits = computed(() => {
  return props.history.reduce((sum, item) => sum + (parseFloat(item.debit) || 0), 0);
});

const totalCredits = computed(() => {
  return props.history.reduce((sum, item) => sum + (parseFloat(item.credit) || 0), 0);
});

const outstandingBalance = computed(() => {
  return props.history.length > 0 ? parseFloat(props.history[0].running_balance) : 0;
});

// Filters for Product-wise Report
const filterInvoiceSearch = ref('');
const filterProductSearch = ref('');
const filterStartDate = ref('');
const filterEndDate = ref('');
const expandedProductIds = ref(new Set());

const toggleExpand = (productId) => {
  const next = new Set(expandedProductIds.value);
  if (next.has(productId)) {
    next.delete(productId);
  } else {
    next.add(productId);
  }
  expandedProductIds.value = next;
};

// Filtered raw product history entries
const filteredProductHistory = computed(() => {
  let list = props.productHistory || [];

  if (filterInvoiceSearch.value.trim()) {
    const q = filterInvoiceSearch.value.toLowerCase().trim();
    list = list.filter(item => (item.invoice_no || '').toLowerCase().includes(q));
  }

  if (filterProductSearch.value.trim()) {
    const q = filterProductSearch.value.toLowerCase().trim();
    list = list.filter(item => 
      (item.product_name || '').toLowerCase().includes(q) ||
      (item.product_code || '').toLowerCase().includes(q)
    );
  }

  if (filterStartDate.value) {
    list = list.filter(item => item.date && item.date >= filterStartDate.value);
  }
  if (filterEndDate.value) {
    list = list.filter(item => item.date && item.date <= filterEndDate.value);
  }

  return list;
});

// Grouped/consolidated products
const aggregatedProducts = computed(() => {
  const map = new Map();

  for (const item of filteredProductHistory.value) {
    const key = item.product_id;
    if (!map.has(key)) {
      map.set(key, {
        product_id: item.product_id,
        product_name: item.product_name,
        product_code: item.product_code,
        unit_type: item.unit_type || 'Pcs',
        description: item.description,
        total_quantity: 0,
        total_subtotal: 0,
        total_gst: 0,
        total_amount: 0,
        rates: [],
        transactions: []
      });
    }

    const entry = map.get(key);
    entry.total_quantity += parseFloat(item.quantity) || 0;
    entry.total_subtotal += parseFloat(item.subtotal) || 0;
    entry.total_gst += parseFloat(item.gst_amount) || 0;
    entry.total_amount += parseFloat(item.total_amount) || 0;
    const priceVal = parseFloat(item.price) || 0;
    if (priceVal > 0 && !entry.rates.includes(priceVal)) {
      entry.rates.push(priceVal);
    }
    entry.transactions.push(item);
  }

  return Array.from(map.values()).map(p => {
    let rateDisplay = '₹0.00';
    if (p.rates.length === 1) {
      rateDisplay = `₹${p.rates[0].toFixed(2)}`;
    } else if (p.rates.length > 1) {
      rateDisplay = `₹${Math.min(...p.rates).toFixed(2)} - ₹${Math.max(...p.rates).toFixed(2)}`;
    } else if (p.total_quantity !== 0) {
      rateDisplay = `₹${Math.abs(p.total_subtotal / p.total_quantity).toFixed(2)}`;
    }

    return {
      ...p,
      rate_display: rateDisplay,
    };
  });
});

// Summary calculations for Product-wise Report
const productSummary = computed(() => {
  const list = filteredProductHistory.value;
  const totalQty = list.reduce((sum, i) => sum + (parseFloat(i.quantity) || 0), 0);
  const totalGst = list.reduce((sum, i) => sum + (parseFloat(i.gst_amount) || 0), 0);
  const totalAmount = list.reduce((sum, i) => sum + (parseFloat(i.total_amount) || 0), 0);
  const uniqueProducts = new Set(list.map(i => i.product_id)).size;
  return {
    totalQty,
    totalGst,
    totalAmount,
    uniqueProducts,
  };
});

const resetProductFilters = () => {
  filterInvoiceSearch.value = '';
  filterProductSearch.value = '';
  filterStartDate.value = '';
  filterEndDate.value = '';
  expandedProductIds.value = new Set();
};

// Dynamic PDF download URL depending on active tab (Invoice vs Product)
const downloadPdfUrl = computed(() => {
  if (activeTab.value === 'product') {
    const params = new URLSearchParams();
    if (filterInvoiceSearch.value) params.append('invoice', filterInvoiceSearch.value);
    if (filterProductSearch.value) params.append('product', filterProductSearch.value);
    if (filterStartDate.value) params.append('from', filterStartDate.value);
    if (filterEndDate.value) params.append('to', filterEndDate.value);
    const qs = params.toString();
    return `/paymentSupplier/${props.supplier.id}/product-report/download-pdf${qs ? '?' + qs : ''}`;
  } else {
    return `/paymentSupplier/${props.supplier.id}/history/download-pdf`;
  }
});

// Row click details navigation
const handleRowClick = (item) => {
  if (item.type === 'Purchase') {
    router.visit(route('purchase.show', item.ref_id));
  } else if (item.type === 'Payment' && item.purchase_id) {
    router.visit(route('purchase.show', item.purchase_id));
  } else if (item.type === 'Return') {
    window.open(route('purchase-return.pdf', item.ref_id), '_blank');
  }
};

const deletePayment = (item, event) => {
  if (event) event.stopPropagation();
  Swal.fire({
    title: 'Delete Payment?',
    text: 'Deleting this payment will automatically rearrange supplier purchase bills in FIFO order.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#dc2626',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, Delete & Rearrange',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({ title: 'Deleting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
      axios.delete(`/paymentSupplier/destroy/${item.ref_id}`)
        .then((response) => {
          Swal.fire('Deleted!', response.data.message, 'success').then(() => {
            window.location.reload();
          });
        })
        .catch((error) => {
          Swal.fire('Error', error.response?.data?.message || 'Failed to delete payment.', 'error');
        });
    }
  });
};
</script>

<template>
    <Head title="Supplier Account Ledger & Product Report">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex items-center justify-between mb-8">
              <div class="flex items-center gap-3">
                <a href="/supplier" class="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-600 hover:text-[#2e2c92] hover:border-[#2e2c92] transition-colors duration-200">
                  <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                  <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Statement & Report</span>
                  <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Supplier Account Ledger</h1>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <a 
                  :href="downloadPdfUrl"
                  target="_blank"
                  class="flex items-center justify-center gap-2 px-4 py-2 bg-[#2e2c92] hover:bg-[#1f1d6b] text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-200"
                >
                  <i class="fa fa-file-pdf-o"></i> 
                  <span>{{ activeTab === 'product' ? 'Download Product Report PDF' : 'Download Statement PDF' }}</span>
                </a>
              </div>
            </div>

            <!-- Supplier Info & Stats Dashboard -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
              <!-- Supplier Profile Card -->
              <div class="bg-gradient-to-br from-[#2e2c92] to-[#4c49d8] text-white p-6 rounded-2xl shadow-lg relative overflow-hidden flex flex-col justify-between">
                <!-- Decorative absolute shapes -->
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white opacity-10 rounded-full"></div>
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white opacity-5 rounded-full"></div>
                
                <div>
                  <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold border border-white/30 shadow-inner">
                      {{ (supplier.name || 'S').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <h3 class="text-xl font-bold tracking-wide">{{ supplier.name }}</h3>
                      <span class="text-sm text-indigo-100 font-medium">Supplier Profile</span>
                    </div>
                  </div>
                  
                  <div class="space-y-2.5 text-sm">
                    <div class="flex items-center gap-2 text-indigo-100">
                      <i class="fa fa-envelope w-5 opacity-85"></i>
                      <span>{{ supplier.email || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-indigo-100">
                      <i class="fa fa-phone w-5 opacity-85"></i>
                      <span>{{ supplier.phone || 'N/A' }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Stats Summary Card 1 (Net Outstanding Due) -->
              <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                  <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Outstanding Balance</span>
                  <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-[#2e2c92] border border-indigo-100">
                    <i class="fa fa-balance-scale"></i>
                  </div>
                </div>
                <div>
                  <div class="text-2xl font-bold" :class="outstandingBalance > 0 ? 'text-red-600' : 'text-emerald-600'">
                    ₹{{ Math.abs(outstandingBalance).toFixed(2) }} 
                    <span class="text-sm font-medium">{{ outstandingBalance >= 0 ? 'Cr' : 'Dr' }}</span>
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Pending dues (Cr) or advance excess (Dr)</p>
                </div>
              </div>

              <!-- Stats Summary Card 2 (Quick Summary breakdown) -->
              <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                  <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Activity Summary</span>
                  <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-[#2e2c92] border border-indigo-100">
                    <i class="fa fa-list-alt"></i>
                  </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <span class="text-xs text-gray-400 block font-medium uppercase">Total Debit (Pay/Ret)</span>
                    <span class="text-lg font-bold text-gray-700">₹{{ totalDebits.toFixed(2) }}</span>
                  </div>
                  <div>
                    <span class="text-xs text-gray-400 block font-medium uppercase">Total Credit (Purchases)</span>
                    <span class="text-lg font-bold text-gray-700">₹{{ totalCredits.toFixed(2) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Report Switcher Tabs -->
            <div class="flex items-center gap-2 mb-6 border-b border-gray-200 pb-3">
              <button
                @click="activeTab = 'invoice'"
                class="flex items-center gap-2.5 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 shadow-sm"
                :class="activeTab === 'invoice' ? 'bg-[#2e2c92] text-white shadow-indigo-200' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
              >
                <i class="fa fa-file-text-o"></i>
                Invoice-wise Report (Ledger)
                <span 
                  class="px-2 py-0.5 text-xs rounded-full"
                  :class="activeTab === 'invoice' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'"
                >
                  {{ history.length }}
                </span>
              </button>

              <button
                @click="activeTab = 'product'"
                class="flex items-center gap-2.5 px-5 py-2.5 rounded-xl font-bold text-sm transition-all duration-200 shadow-sm"
                :class="activeTab === 'product' ? 'bg-[#2e2c92] text-white shadow-indigo-200' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200'"
              >
                <i class="fa fa-cube"></i>
                Product-wise Report
                <span 
                  class="px-2 py-0.5 text-xs rounded-full"
                  :class="activeTab === 'product' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600'"
                >
                  {{ aggregatedProducts.length }}
                </span>
              </button>
            </div>

            <!-- TAB 1: Invoice-wise Report (Ledger Details Table) -->
            <div v-if="activeTab === 'invoice'" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
              <div class="mb-6 flex items-center justify-between">
                <div>
                  <h2 class="text-lg font-bold text-gray-900">Ledger Details</h2>
                  <p class="text-sm text-gray-500 mt-0.5">Double-entry record of bills, payments, and returns. Click any row for details.</p>
                </div>
              </div>
              
              <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600">
                    <thead class="bg-[#2e2c92] text-white">
                        <tr>
                            <th class="px-6 py-4 rounded-tl-lg">Date</th>
                            <th class="px-6 py-4">Vch Type</th>
                            <th class="px-6 py-4">Particulars</th>
                            <th class="px-6 py-4 text-right">Debit (Dr)</th>
                            <th class="px-6 py-4 text-right">Credit (Cr)</th>
                            <th class="px-6 py-4 text-right">Balance</th>
                            <th class="px-6 py-4 text-center rounded-tr-lg">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr 
                          v-for="(item, index) in history" 
                          :key="index" 
                          @click="handleRowClick(item)"
                          class="hover:bg-indigo-50/50 cursor-pointer transition duration-150"
                        >
                            <td class="px-6 py-4 font-medium text-gray-900">{{ formatDate(item.date) }}</td>
                            <td class="px-6 py-4">
                              <span v-if="item.type === 'Purchase'" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Bill</span>
                              <span v-else-if="item.type === 'Payment'" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200">Payment</span>
                              <span v-else-if="item.type === 'Return'" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200">Return</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                              {{ item.particulars }} 
                              <span v-if="item.payment_method && item.payment_method !== 'Bill'" class="text-xs text-gray-400">({{ item.payment_method }})</span>
                            </td>
                            <td class="px-6 py-4 text-right text-red-600 font-semibold">
                              {{ item.debit > 0 ? '₹' + item.debit.toFixed(2) : '--' }}
                            </td>
                            <td class="px-6 py-4 text-right text-emerald-600 font-semibold">
                              {{ item.credit > 0 ? '₹' + item.credit.toFixed(2) : '--' }}
                            </td>
                            <td class="px-6 py-4 text-right text-gray-900 font-bold">
                              ₹{{ Math.abs(item.running_balance).toFixed(2) }} {{ item.running_balance >= 0 ? 'Cr' : 'Dr' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                              <button
                                v-if="item.type === 'Payment'"
                                @click.stop="deletePayment(item, $event)"
                                class="text-white bg-red-600 hover:bg-red-700 px-2.5 py-1 rounded text-xs transition shadow-sm"
                                title="Delete Payment"
                              >
                                <i class="fa fa-trash"></i>
                              </button>
                              <span v-else class="text-gray-400 font-mono text-xs">—</span>
                            </td>
                        </tr>
                        <tr v-if="!history.length">
                            <td colspan="7" class="px-6 py-8 text-center text-gray-400">No ledger entries found.</td>
                        </tr>
                    </tbody>
                </table>
              </div>
            </div>

            <!-- TAB 2: Product-wise Report -->
            <div v-if="activeTab === 'product'" class="space-y-6">
              <!-- Summary Mini-Cards for Product Purchases -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Unique Products Purchased</span>
                    <span class="text-2xl font-extrabold text-[#2e2c92]">{{ productSummary.uniqueProducts }}</span>
                  </div>
                  <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#2e2c92] flex items-center justify-center text-xl">
                    <i class="fa fa-cubes"></i>
                  </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Total Quantity (Units)</span>
                    <span class="text-2xl font-extrabold text-emerald-600">{{ productSummary.totalQty.toFixed(2) }}</span>
                  </div>
                  <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa fa-cube"></i>
                  </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex items-center justify-between">
                  <div>
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Total Product Purchases Amount</span>
                    <span class="text-2xl font-extrabold text-[#2e2c92]">₹{{ productSummary.totalAmount.toFixed(2) }}</span>
                  </div>
                  <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                    <i class="fa fa-credit-card"></i>
                  </div>
                </div>
              </div>

              <!-- Product Filters Section -->
              <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center gap-2">
                    <i class="fa fa-filter text-[#2e2c92]"></i>
                    <h3 class="text-base font-bold text-gray-900">Filter Product Purchases</h3>
                  </div>
                  <button 
                    @click="resetProductFilters"
                    class="text-xs font-semibold text-[#2e2c92] hover:text-indigo-800 flex items-center gap-1 transition-colors"
                  >
                    <i class="fa fa-refresh"></i> Reset Filters
                  </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                  <!-- Search by Invoice / Bill -->
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Search by Bill / Return #</label>
                    <div class="relative">
                      <i class="fa fa-file-text-o absolute left-3 top-3 text-gray-400 text-sm"></i>
                      <input 
                        type="text" 
                        v-model="filterInvoiceSearch" 
                        placeholder="Bill / Return #..."
                        class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                      />
                    </div>
                  </div>

                  <!-- Search by Product -->
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">Search by Product</label>
                    <div class="relative">
                      <i class="fa fa-cube absolute left-3 top-3 text-gray-400 text-sm"></i>
                      <input 
                        type="text" 
                        v-model="filterProductSearch" 
                        placeholder="Product name or SKU..."
                        class="w-full pl-9 pr-3 py-2 text-sm bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                      />
                    </div>
                  </div>

                  <!-- From Date -->
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">From Date</label>
                    <input 
                      type="date" 
                      v-model="filterStartDate"
                      class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    />
                  </div>

                  <!-- To Date -->
                  <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">To Date</label>
                    <input 
                      type="date" 
                      v-model="filterEndDate"
                      class="w-full px-3 py-2 text-sm bg-gray-50 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                    />
                  </div>
                </div>
              </div>

              <!-- Product-wise Report Table -->
              <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                <div class="mb-4 flex items-center justify-between">
                  <div>
                    <h2 class="text-lg font-bold text-gray-900">Product-wise Purchase Details</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Consolidated product purchases from {{ supplier.name }}. Click any product row to see transaction breakdown.</p>
                  </div>
                  <span class="text-xs font-medium text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                    Showing {{ aggregatedProducts.length }} products
                  </span>
                </div>

                <div class="overflow-x-auto">
                  <table class="w-full text-sm text-left text-gray-600">
                    <thead class="bg-[#2e2c92] text-white">
                      <tr>
                        <th class="px-5 py-3.5 rounded-tl-lg w-16 text-center">S No</th>
                        <th class="px-5 py-3.5">Product Name</th>
                        <th class="px-5 py-3.5 text-center">Total Quantity</th>
                        <th class="px-5 py-3.5 text-right rounded-tr-lg">Unit Rate</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                      <template v-for="(prod, index) in aggregatedProducts" :key="prod.product_id">
                        <!-- Consolidated Product Row -->
                        <tr 
                          @click="toggleExpand(prod.product_id)"
                          class="hover:bg-indigo-50/50 cursor-pointer transition duration-150"
                          :class="{'bg-indigo-50/20': expandedProductIds.has(prod.product_id)}"
                        >
                          <td class="px-5 py-4 text-center font-medium text-gray-500">
                            {{ index + 1 }}
                          </td>
                          <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                              <span 
                                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 text-gray-500 text-xs transition-transform"
                                :class="{'rotate-90 text-[#2e2c92] bg-indigo-100': expandedProductIds.has(prod.product_id)}"
                              >
                                <i class="fa fa-chevron-right"></i>
                              </span>
                              <div>
                                <div class="font-bold text-gray-900 text-base">{{ prod.product_name }}</div>
                                <div v-if="prod.product_code" class="text-xs text-gray-400">SKU: {{ prod.product_code }}</div>
                                <div v-if="prod.transactions.length > 1" class="text-xs text-[#2e2c92] font-semibold mt-0.5">
                                  <i class="fa fa-list-alt mr-1"></i>{{ prod.transactions.length }} transactions (click to view)
                                </div>
                              </div>
                            </div>
                          </td>
                          <td class="px-5 py-4 text-center whitespace-nowrap font-extrabold text-base" :class="prod.total_quantity < 0 ? 'text-rose-600' : 'text-emerald-700'">
                            {{ prod.total_quantity.toFixed(2) }} {{ prod.unit_type }}
                          </td>
                          <td class="px-5 py-4 text-right font-semibold text-gray-900 whitespace-nowrap">
                            {{ prod.rate_display }}
                          </td>
                        </tr>

                        <!-- Expandable Transaction Breakdown Sub-table -->
                        <tr v-if="expandedProductIds.has(prod.product_id)" class="bg-gray-50/80">
                          <td colspan="4" class="px-6 py-4">
                            <div class="bg-white rounded-xl border border-indigo-100 shadow-sm p-4 ml-6">
                              <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-[#2e2c92] uppercase tracking-wider">
                                  <i class="fa fa-info-circle mr-1"></i> Transaction Breakdown: {{ prod.product_name }}
                                </span>
                                <span class="text-xs text-gray-400">
                                  Total: {{ prod.transactions.length }} entry/entries
                                </span>
                              </div>
                              <table class="w-full text-xs text-left text-gray-600">
                                <thead class="bg-gray-100 text-gray-700">
                                  <tr>
                                    <th class="px-3 py-2 rounded-l">Date</th>
                                    <th class="px-3 py-2">Bill / Return #</th>
                                    <th class="px-3 py-2">Type</th>
                                    <th class="px-3 py-2 text-center">Quantity</th>
                                    <th class="px-3 py-2 text-right rounded-r">Unit Rate</th>
                                  </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                  <tr v-for="t in prod.transactions" :key="t.id" class="hover:bg-gray-50/80">
                                    <td class="px-3 py-2.5 font-medium whitespace-nowrap">{{ formatDate(t.date) }}</td>
                                    <td class="px-3 py-2.5 font-semibold text-[#2e2c92] whitespace-nowrap">
                                      {{ t.invoice_no }}
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                      <span v-if="t.type === 'Purchase'" class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-800">Purchase</span>
                                      <span v-else class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-rose-100 text-rose-800">Return</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-bold" :class="t.quantity < 0 ? 'text-rose-600' : 'text-gray-800'">
                                      {{ t.quantity }} {{ t.unit_type }}
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-medium">₹{{ parseFloat(t.price).toFixed(2) }}</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </td>
                        </tr>
                      </template>

                      <tr v-if="!aggregatedProducts.length">
                        <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                          <div class="flex flex-col items-center justify-center gap-2">
                            <i class="fa fa-cubes text-3xl text-gray-300"></i>
                            <span class="font-medium text-gray-500">No product purchases found matching criteria.</span>
                            <span class="text-xs text-gray-400">Try adjusting your filters or search keywords.</span>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
