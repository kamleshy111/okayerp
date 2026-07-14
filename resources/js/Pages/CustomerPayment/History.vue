<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';


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
const totalDebits = computed(() => {
  return props.history.reduce((sum, item) => sum + (parseFloat(item.debit) || 0), 0);
});

const totalCredits = computed(() => {
  return props.history.reduce((sum, item) => sum + (parseFloat(item.credit) || 0), 0);
});

const outstandingBalance = computed(() => {
  return props.history.length > 0 ? parseFloat(props.history[0].running_balance) : 0;
});

// Row click details navigation
const handleRowClick = (item) => {
  if (item.type === 'Sale') {
    router.visit(route('sale.show', item.ref_id));
  } else if (item.type === 'Payment') {
    window.open(route('paymentsCustomer.receipt.show', { source: 'payment', id: item.ref_id }), '_blank');
  } else if (item.type === 'Return') {
    window.open(route('sale-return.pdf', item.ref_id), '_blank');
  }
};

const sendingWhatsApp = ref(false);

const sendStatementWhatsApp = async () => {
  if (sendingWhatsApp.value) return;
  const phone = props.customer?.phone || '';
  if (!phone) {
    Swal.fire('No Phone Number', 'This customer does not have a phone number on file.', 'warning');
    return;
  }
  sendingWhatsApp.value = true;
  try {
    const response = await axios.post(`/whatsapp/send-statement/${props.customer.id}`);
    Swal.fire('Sent!', response.data.message, 'success');
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Failed to send WhatsApp message.', 'error');
  } finally {
    sendingWhatsApp.value = false;
  }
};
</script>

<template>
    <Head title="Customer Account Ledger">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6 max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-8">
              <div class="flex items-center gap-3">
                <a href="/paymentsCustomer" class="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-600 hover:text-[#2e2c92] hover:border-[#2e2c92] transition-colors duration-200">
                  <i class="fa fa-arrow-left"></i>
                </a>
                <div>
                  <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Statement</span>
                  <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Customer Account Ledger</h1>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <a 
                  :href="`/paymentsCustomer/${customer.id}/history/download-pdf`"
                  target="_blank"
                  class="flex items-center justify-center gap-2 px-4 py-2 bg-[#2e2c92] hover:bg-[#1f1d6b] text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-200"
                >
                  <i class="fa fa-file-pdf-o"></i> Download Statement PDF
                </a>
                <!-- WhatsApp Statement -->
                <button
                  @click="sendStatementWhatsApp"
                  :disabled="sendingWhatsApp"
                  class="flex items-center justify-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-200"
                >
                  <i :class="sendingWhatsApp ? 'fa fa-spinner fa-spin' : 'fa fa-whatsapp'"></i>
                  {{ sendingWhatsApp ? 'Sending...' : 'Send on WhatsApp' }}
                </button>
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
                    <span class="text-sm font-medium">{{ outstandingBalance >= 0 ? 'Dr' : 'Cr' }}</span>
                  </div>
                  <p class="text-xs text-gray-400 mt-1">Pending dues (Dr) or advance excess (Cr)</p>
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
                    <span class="text-xs text-gray-400 block font-medium uppercase">Total Debit (Sales)</span>
                    <span class="text-lg font-bold text-gray-700">₹{{ totalDebits.toFixed(2) }}</span>
                  </div>
                  <div>
                    <span class="text-xs text-gray-400 block font-medium uppercase">Total Credit (Pay/Ret)</span>
                    <span class="text-lg font-bold text-gray-700">₹{{ totalCredits.toFixed(2) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Detailed History Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
              <div class="mb-6 flex items-center justify-between">
                <div>
                  <h2 class="text-lg font-bold text-gray-900">Ledger Details</h2>
                  <p class="text-sm text-gray-500 mt-0.5">Double-entry record of invoices, receipts, and returns. Click any row for details.</p>
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
                            <th class="px-6 py-4 text-right rounded-tr-lg">Balance</th>
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
                              <span v-if="item.type === 'Sale'" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Sale</span>
                              <span v-else-if="item.type === 'Payment'" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200">Receipt</span>
                              <span v-else-if="item.type === 'Return'" class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200">Return</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500">
                              {{ item.particulars }} 
                              <span v-if="item.payment_method && item.payment_method !== 'Invoice'" class="text-xs text-gray-400">({{ item.payment_method }})</span>
                            </td>
                            <td class="px-6 py-4 text-right text-red-600 font-semibold">
                              {{ item.debit > 0 ? '₹' + item.debit.toFixed(2) : '--' }}
                            </td>
                            <td class="px-6 py-4 text-right text-emerald-600 font-semibold">
                              {{ item.credit > 0 ? '₹' + item.credit.toFixed(2) : '--' }}
                            </td>
                            <td class="px-6 py-4 text-right text-gray-900 font-bold">
                              ₹{{ Math.abs(item.running_balance).toFixed(2) }} {{ item.running_balance >= 0 ? 'Dr' : 'Cr' }}
                            </td>
                        </tr>
                        <tr v-if="!history.length">
                            <td colspan="6" class="px-6 py-8 text-center text-gray-400">No ledger entries found.</td>
                        </tr>
                    </tbody>
                </table>
              </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
