<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  payment: {
    type: Object,
    required: true
  },
  source: {
    type: String,
    required: true
  }
});

const page = usePage();
const store = computed(() => page.props.auth.user);

// Format dates nicely
const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const day = String(date.getDate()).padStart(2, '0');
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months[date.getMonth()];
  const year = String(date.getFullYear()).slice(-2);
  return `${day}-${month}-${year}`;
};

// Convert number to words (simple version)
const convertNumberToWords = (amount) => {
  if (!amount) return 'Zero';
  // This is a simple fallback. Real conversion might require a library or complex logic in JS,
  // but we can just display the numeric amount if it's too complex, or implement a basic one.
  return `₹ ${Number(amount).toFixed(2)}`;
};
</script>

<template>
  <Head :title="`Payment Receipt #${payment.id}`">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6 max-w-5xl mx-auto">
      <!-- Breadcrumb & Top Actions -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 no-print">
        <div class="flex items-center gap-3">
          <a href="/paymentsCustomer" class="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-600 hover:text-[#2e2c92] hover:border-[#2e2c92] transition-colors duration-200">
            <i class="fa fa-arrow-left"></i>
          </a>
          <div>
            <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Receipt</span>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Payment Details</h1>
          </div>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <!-- Download PDF -->
          <a 
            :href="`/paymentsCustomer/receipt/${source}/${payment.id}/pdf`"
            target="_blank"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-[#2e2c92] hover:bg-[#1f1d6b] text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-200 w-full sm:w-auto"
          >
            <i class="fa fa-file-pdf-o"></i> Download PDF
          </a>
        </div>
      </div>

      <!-- Receipt Content Container -->
      <div id="print-area" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 md:p-12">
        <!-- Receipt Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-200 pb-8 mb-8 gap-4">
          <div>
            <div class="text-2xl font-black text-[#2e2c92] uppercase tracking-wider">
              {{ store?.name || 'COMPANY NAME' }}
            </div>
            <p class="text-sm text-gray-500 mt-1">Payment Receipt</p>
          </div>
          <div class="text-left md:text-right">
            <h2 class="text-2xl font-bold text-gray-900">RECEIPT</h2>
            <p class="text-sm text-gray-500 mt-1">Receipt #: <span class="font-semibold text-gray-800">{{ payment.id }}</span></p>
            <p class="text-sm text-gray-500">Receipt Date: <span class="font-semibold text-gray-800">{{ formatDate(payment.created_at) }}</span></p>
          </div>
        </div>

        <!-- Addresses / Billing info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
          <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">From</h3>
            <div class="text-sm text-gray-700 space-y-1">
              <p class="font-bold text-gray-900 text-base">{{ store?.name || 'COMPANY NAME' }}</p>
              <p v-if="store?.address" class="whitespace-pre-line text-gray-600">{{ store.address }}</p>
              <p v-if="store?.phone" class="text-gray-600"><span class="font-medium text-gray-900">Phone:</span> {{ store.phone }}</p>
              <p v-if="store?.gstin" class="text-gray-600"><span class="font-medium text-gray-900">GSTIN:</span> {{ store.gstin }}</p>
            </div>
          </div>

          <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ payment.is_return ? 'Refunded To' : 'Received From' }}</h3>
            <div class="text-sm text-gray-700 space-y-1">
              <p class="font-bold text-gray-900 text-base">{{ payment.customer_name || 'N/A' }}</p>
              <p v-if="payment.address" class="whitespace-pre-line text-gray-600">{{ payment.address }}</p>
              <p v-if="payment.phone" class="text-gray-600"><span class="font-medium text-gray-900">Phone:</span> {{ payment.phone }}</p>
              <p v-if="payment.email" class="text-gray-600"><span class="font-medium text-gray-900">Email:</span> {{ payment.email }}</p>
              <p v-if="payment.gst_number" class="text-gray-600"><span class="font-medium text-gray-900">GSTIN:</span> {{ payment.gst_number }}</p>
            </div>
          </div>
        </div>

        <!-- Receipt Table -->
        <div class="border rounded-xl overflow-hidden mb-8 border-gray-200">
          <table class="w-full text-left text-sm text-gray-600">
            <thead class="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200">
              <tr>
                <th scope="col" class="px-6 py-4">Description</th>
                <th scope="col" class="px-6 py-4">Payment Date</th>
                <th scope="col" class="px-6 py-4">Payment Method</th>
                <th scope="col" class="px-6 py-4 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr 
                v-for="hist in payment.payment_history" 
                :key="hist.id" 
                class="transition-colors duration-150"
                :class="hist.id === payment.id ? 'bg-blue-50/50 border-l-4 border-blue-500 hover:bg-blue-50' : 'hover:bg-gray-50'"
              >
                <td class="px-6 py-4 font-medium text-gray-900">
                  <div class="font-semibold text-gray-900 text-sm">
                    {{ hist.reason }}
                  </div>
                  <div class="text-xs text-gray-500 mt-1" v-if="hist.note">
                    Note: {{ hist.note }}
                  </div>
                </td>
                <td class="px-6 py-4 text-gray-600 font-medium text-sm">
                  {{ formatDate(hist.payment_date) }}
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ hist.payment_method }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                  ₹{{ Number(hist.amount).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Summary -->
        <div class="flex flex-col md:flex-row justify-between items-start gap-8 mt-8">
          <!-- Left side (Amount in Words) -->
          <div class="w-full md:w-1/2">
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
              <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Amount in Words</h3>
              <p class="text-sm font-semibold text-gray-800 italic">
                {{ payment.amount_in_words }}
              </p>
            </div>
          </div>
          
          <!-- Right side Totals -->
          <div class="w-full md:w-5/12 bg-gray-50 rounded-xl p-6 border border-gray-100">
            <div class="divide-y divide-gray-100 text-sm">
              <div class="flex justify-between py-3">
                <span class="font-semibold text-gray-700 text-base">Amount {{ payment.is_return ? 'Refunded' : 'Received' }}</span>
                <span class="font-black text-[#2e2c92] text-lg">₹{{ Number(payment.amount).toFixed(2) }}</span>
              </div>
              
              <div v-if="payment.sale_id && payment.sale_grand_total !== null" class="pt-3 space-y-2">
                <div class="flex justify-between py-2 border-t border-gray-200">
                  <span class="text-gray-500 font-medium">Grand Total</span>
                  <span class="text-[#2e2c92] font-bold">₹{{ Number(payment.sale_grand_total).toFixed(2) }}</span>
                </div>
                <div class="flex justify-between py-2">
                  <span class="text-gray-500 font-medium">Paid</span>
                  <span class="text-emerald-600 font-semibold">₹{{ Number(payment.sale_total_paid).toFixed(2) }}</span>
                </div>
                
                <div v-if="Number(payment.sale_remaining) > 0" class="flex justify-between py-3 text-base font-bold bg-white px-4 rounded-lg mt-2 border border-gray-200">
                  <span class="text-gray-700">Balance Due</span>
                  <span class="text-rose-600">₹{{ Number(payment.sale_remaining).toFixed(2) }}</span>
                </div>
                <div v-else class="flex justify-between py-3 text-base font-bold bg-white px-4 rounded-lg mt-2 border border-gray-200">
                  <span class="text-gray-700">Balance Due</span>
                  <span class="text-emerald-600">₹0.00</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-12 pt-8 border-t border-gray-100 flex justify-between items-end">
          <div>
            <p class="text-sm font-medium text-gray-900">Authorized Signatory</p>
            <div class="h-12 border-b border-gray-300 w-48 mt-8 mb-2"></div>
            <p class="text-xs text-gray-500">For {{ store?.name || 'COMPANY NAME' }}</p>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
