<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
  arData: {
    type: Array,
    required: true
  },
  arSummary: {
    type: Object,
    required: true
  },
  apData: {
    type: Array,
    required: true
  },
  apSummary: {
    type: Object,
    required: true
  }
});

const activeTab = ref('ar');

// Helper to format currency
const formatCurrency = (val) => {
  return new Intl.NumberFormat('en-IN', {
    style: 'currency',
    currency: 'INR'
  }).format(val);
};

// Column definitions for AR DataTable
const arColumns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    className: 'text-center border-b border-gray-200'
  },
  { 
    data: 'name', 
    title: 'Customer Name',
    render: (data) => `<span class="font-semibold text-gray-700">${data}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'phone', 
    title: 'Phone',
    render: (data) => `<span class="text-gray-600">${data}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'total_due', 
    title: 'Total Due',
    render: (data) => `<span class="font-bold text-gray-900">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_0_30', 
    title: '0-30 Days',
    render: (data) => `<span class="text-green-600 font-medium">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_31_60', 
    title: '31-60 Days',
    render: (data) => `<span class="text-yellow-600 font-medium">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_61_90', 
    title: '61-90 Days',
    render: (data) => `<span class="text-orange-600 font-medium">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_90_plus', 
    title: '90+ Days',
    render: (data) => `<span class="${data > 0 ? 'text-red-600 font-bold' : 'text-gray-600'}">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
];

// Column definitions for AP DataTable
const apColumns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    className: 'text-center border-b border-gray-200'
  },
  { 
    data: 'name', 
    title: 'Supplier Name',
    render: (data) => `<span class="font-semibold text-gray-700">${data}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'phone', 
    title: 'Phone',
    render: (data) => `<span class="text-gray-600">${data}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'total_due', 
    title: 'Total Due',
    render: (data) => `<span class="font-bold text-gray-900">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_0_30', 
    title: '0-30 Days',
    render: (data) => `<span class="text-green-600 font-medium">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_31_60', 
    title: '31-60 Days',
    render: (data) => `<span class="text-yellow-600 font-medium">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_61_90', 
    title: '61-90 Days',
    render: (data) => `<span class="text-orange-600 font-medium">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
  { 
    data: 'bucket_90_plus', 
    title: '90+ Days',
    render: (data) => `<span class="${data > 0 ? 'text-red-600 font-bold' : 'text-gray-600'}">${formatCurrency(data)}</span>`,
    className: 'border-b border-gray-200'
  },
];
</script>

<template>
  <Head title="AR & AP Aging Analysis" />

  <AuthenticatedLayout>
    <div class="p-6 max-w-7xl mx-auto space-y-8">
      
      <!-- Top header with clean design -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
          <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">AR & AP Aging Reports</h1>
          <p class="text-gray-500 mt-1">Monitor credit status, overdue invoices, and outstanding vendor payables.</p>
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-gray-100 p-1.5 rounded-xl flex items-center shadow-inner">
          <button 
            @click="activeTab = 'ar'"
            :class="[
              'px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-300',
              activeTab === 'ar' 
                ? 'bg-white text-[#2e2c92] shadow' 
                : 'text-gray-600 hover:text-gray-900'
            ]"
          >
            📊 Accounts Receivable (AR)
          </button>
          <button 
            @click="activeTab = 'ap'"
            :class="[
              'px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-300',
              activeTab === 'ap' 
                ? 'bg-white text-[#2e2c92] shadow' 
                : 'text-gray-600 hover:text-gray-900'
            ]"
          >
            📉 Accounts Payable (AP)
          </button>
        </div>
      </div>

      <!-- Tab content logic -->
      <div v-if="activeTab === 'ar'" class="space-y-8 animate-fadeIn">
        
        <!-- Summary Cards for AR -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-gradient-to-br from-[#2e2c92] to-[#4c49d8] text-white p-6 rounded-2xl shadow-xl flex flex-col justify-between h-36">
            <span class="text-sm font-medium opacity-85">Total Outstanding Receivables</span>
            <span class="text-3xl font-extrabold">{{ formatCurrency(arSummary.total_receivables) }}</span>
          </div>

          <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-md flex flex-col justify-between h-36 border-l-4 border-l-green-500">
            <span class="text-sm font-medium text-gray-400">Current (0-30 Days)</span>
            <span class="text-3xl font-extrabold text-gray-800">{{ formatCurrency(arSummary.total_0_30) }}</span>
          </div>

          <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-md flex flex-col justify-between h-36 border-l-4 border-l-orange-400">
            <span class="text-sm font-medium text-gray-400">Overdue (31-90 Days)</span>
            <span class="text-3xl font-extrabold text-gray-800">{{ formatCurrency(arSummary.total_31_60 + arSummary.total_61_90) }}</span>
          </div>

          <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-md flex flex-col justify-between h-36 border-l-4 border-l-red-500">
            <span class="text-sm font-medium text-gray-400">High Risk (90+ Days)</span>
            <span class="text-3xl font-extrabold text-red-600">{{ formatCurrency(arSummary.total_90_plus) }}</span>
          </div>
        </div>

        <!-- Accounts Receivable Table -->
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-md">
          <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span>Customer Aging Summary</span>
            <span class="text-xs bg-indigo-50 text-indigo-700 px-2.5 py-0.5 rounded-full font-semibold">FIFO Applied</span>
          </h2>
          <div class="overflow-x-auto">
            <DataTable :data="arData" :columns="arColumns" id="ar-table" class="w-full text-sm">
              <thead class="bg-[#2e2c92] text-white">
                <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Customer Name</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Total Due</th>
                  <th scope="col">0-30 Days</th>
                  <th scope="col">31-60 Days</th>
                  <th scope="col">61-90 Days</th>
                  <th scope="col">90+ Days</th>
                </tr>
              </thead>
            </DataTable>
          </div>
        </div>

      </div>

      <div v-else class="space-y-8 animate-fadeIn">

        <!-- Summary Cards for AP -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-gradient-to-br from-[#7c1d1d] to-[#b91c1c] text-white p-6 rounded-2xl shadow-xl flex flex-col justify-between h-36">
            <span class="text-sm font-medium opacity-85">Total Outstanding Payables</span>
            <span class="text-3xl font-extrabold">{{ formatCurrency(apSummary.total_payables) }}</span>
          </div>

          <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-md flex flex-col justify-between h-36 border-l-4 border-l-green-500">
            <span class="text-sm font-medium text-gray-400">Current (0-30 Days)</span>
            <span class="text-3xl font-extrabold text-gray-800">{{ formatCurrency(apSummary.total_0_30) }}</span>
          </div>

          <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-md flex flex-col justify-between h-36 border-l-4 border-l-orange-400">
            <span class="text-sm font-medium text-gray-400">Overdue (31-90 Days)</span>
            <span class="text-3xl font-extrabold text-gray-800">{{ formatCurrency(apSummary.total_31_60 + apSummary.total_61_90) }}</span>
          </div>

          <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-md flex flex-col justify-between h-36 border-l-4 border-l-red-500">
            <span class="text-sm font-medium text-gray-400">High Risk (90+ Days)</span>
            <span class="text-3xl font-extrabold text-red-600">{{ formatCurrency(apSummary.total_90_plus) }}</span>
          </div>
        </div>

        <!-- Accounts Payable Table -->
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-md">
          <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <span>Supplier Aging Summary</span>
            <span class="text-xs bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full font-semibold">FIFO Applied</span>
          </h2>
          <div class="overflow-x-auto">
            <DataTable :data="apData" :columns="apColumns" id="ap-table" class="w-full text-sm">
              <thead class="bg-[#2e2c92] text-white">
                <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Supplier Name</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Total Due</th>
                  <th scope="col">0-30 Days</th>
                  <th scope="col">31-60 Days</th>
                  <th scope="col">61-90 Days</th>
                  <th scope="col">90+ Days</th>
                </tr>
              </thead>
            </DataTable>
          </div>
        </div>

      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(5px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn {
  animation: fadeIn 0.4s ease-out forwards;
}
</style>
