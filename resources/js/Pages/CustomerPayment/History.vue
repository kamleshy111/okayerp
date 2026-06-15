<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  customer: {
    type: Object,
    required: true
  },
  history: {
    type: Array,
    required: true
  }
});

// Date formatter helper
const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

// Calculate summary stats
const totalReceived = computed(() => {
  return props.history
    .filter(item => item.amount > 0)
    .reduce((sum, item) => sum + parseFloat(item.amount), 0);
});

const totalRefunded = computed(() => {
  return props.history
    .filter(item => item.amount < 0)
    .reduce((sum, item) => sum + Math.abs(parseFloat(item.amount)), 0);
});

const netAmount = computed(() => {
  return props.history.reduce((sum, item) => sum + parseFloat(item.amount), 0);
});

// Column definitions for DataTable
const columns = [
    { 
      data: null,
      title: 'S No',
      render: (data, type, row, meta) => meta.row + 1,
    },
    { 
      data: 'source',
      title: 'Source',
      render: function(data) {
        if (data === 'Sale') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Sale</span>`;
        } else if (data === 'Customer Payment') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200">Direct Payment</span>`;
        } else if (data === 'Return') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200">Return</span>`;
        } else {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200">${data}</span>`;
        }
      }
    },
    { 
      data: 'amount',
      title: 'Amount',
      render: function(data) {
        const val = parseFloat(data);
        if (val < 0) {
          return `<span style="color:#dc2626; font-weight:600;">- ₹${Math.abs(val).toFixed(2)}</span>`;
        } else {
          return `<span style="color:#16a34a; font-weight:600;">+ ₹${val.toFixed(2)}</span>`;
        }
      }
    },
    {  
      data: 'payment_date',
      title: 'Payment Date',
      render: function(data) {
          return formatDate(data);
      }
    },
    { 
      data: 'payment_method',
      title: 'Payment Method'
    }
];
</script>

<template>
    <Head title="Customer Payment History">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto">
            <!-- Breadcrumbs / Top Actions -->
            <div class="flex items-center justify-between mb-8">
              <div class="flex items-center gap-3">
                <a href="/paymentsCustomer" class="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-600 hover:text-[#2e2c92] hover:border-[#2e2c92] transition-colors duration-200">
                  <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                  <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Payments</span>
                  <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Customer Payment History</h1>
                </div>
              </div>
            </div>

            <!-- Customer Info & Stats Dashboard -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
              <!-- Customer Profile Card -->
              <div class="bg-gradient-to-br from-[#2e2c92] to-[#4c49d8] text-white p-6 rounded-2xl shadow-lg relative overflow-hidden flex flex-col justify-between">
                <!-- Decorative absolute shapes -->
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white opacity-10 rounded-full"></div>
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white opacity-5 rounded-full"></div>
                
                <div>
                  <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold border border-white/30 shadow-inner">
                      {{ (customer.name || 'C').charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <h3 class="text-xl font-bold tracking-wide">{{ customer.name }}</h3>
                      <span class="text-sm text-indigo-100 font-medium">Customer Profile</span>
                    </div>
                  </div>
                  
                  <div class="space-y-2.5 text-sm">
                    <div class="flex items-center gap-2 text-indigo-100">
                      <i class="fa fa-envelope w-5 opacity-85"></i>
                      <span>{{ customer.email || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-indigo-100">
                      <i class="fa fa-phone w-5 opacity-85"></i>
                      <span>{{ customer.phone || 'N/A' }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Stats Summary Card 1 (Net Transactions) -->
              <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col justify-between">
                <div class="flex items-center justify-between mb-4">
                  <span class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Net Amount</span>
                  <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center text-[#2e2c92] border border-indigo-100">
                    <i class="fa fa-balance-scale"></i>
                  </div>
                </div>
                <div>
                  <div class="text-2xl font-bold" :class="netAmount < 0 ? 'text-red-600' : 'text-emerald-600'">
                    {{ netAmount < 0 ? '-' : '+' }} ₹{{ Math.abs(netAmount).toFixed(2) }}
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Total combined balance of sales, payments and returns</p>
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
                    <span class="text-xs text-gray-400 block font-medium uppercase">Total Received</span>
                    <span class="text-lg font-bold text-emerald-600">+ ₹{{ totalReceived.toFixed(2) }}</span>
                  </div>
                  <div>
                    <span class="text-xs text-gray-400 block font-medium uppercase">Total Refunded</span>
                    <span class="text-lg font-bold text-red-600">- ₹{{ totalRefunded.toFixed(2) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detailed History Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
              <div class="mb-6 flex items-center justify-between">
                <div>
                  <h2 class="text-lg font-bold text-gray-900">Transaction History</h2>
                  <p class="text-sm text-gray-500 mt-0.5">Chronological record of all client activities</p>
                </div>
              </div>
              
              <div class="overflow-x-auto">
                <DataTable :data="history" :columns="columns" id="customer-history-table" class="w-full text-sm text-left text-gray-600">
                    <thead class="bg-[#2e2c92] text-white main-head-table">
                        <tr>
                            <th scope="col" class="px-6 py-4">S No</th>
                            <th scope="col" class="px-6 py-4">Source</th>
                            <th scope="col" class="px-6 py-4">Amount</th>
                            <th scope="col" class="px-6 py-4">Payment Date</th>
                            <th scope="col" class="px-6 py-4">Payment Method</th>
                        </tr>
                    </thead>
                </DataTable>
              </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
