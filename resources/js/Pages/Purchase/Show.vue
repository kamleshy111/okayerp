<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  purchase: {
    type: Object,
    required: true
  },
  allocatedPayment: {
    type: [Number, String],
    default: 0
  }
});

// Format dates nicely
const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
};

// Print utility
const triggerPrint = () => {
  window.print();
};
</script>

<template>
  <Head :title="`Purchase Bill #${purchase.id}`">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6 max-w-5xl mx-auto">
      <!-- Breadcrumb & Top Actions -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 no-print">
        <div class="flex items-center gap-3">
          <a href="/purchase" class="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-600 hover:text-[#2e2c92] hover:border-[#2e2c92] transition-colors duration-200">
            <i class="fa fa-arrow-left"></i>
          </a>
          <div>
            <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Purchase Bill</span>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Purchase Details</h1>
          </div>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <!-- Print Button -->
          <button 
            @click="triggerPrint"
            class="flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 shadow-sm transition-colors duration-200 w-full sm:w-auto"
          >
            <i class="fa fa-print"></i> Print
          </button>
          
          <!-- Download PDF -->
          <a 
            :href="`/purchase/${purchase.id}/download-pdf`"
            target="_blank"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-[#2e2c92] hover:bg-[#1f1d6b] text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-200 w-full sm:w-auto"
          >
            <i class="fa fa-file-pdf-o"></i> Download PDF
          </a>
        </div>
      </div>

      <!-- Invoice Content Container -->
      <div id="print-area" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 md:p-12">
        <!-- Invoice Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-200 pb-8 mb-8 gap-4">
          <div>
            <div class="text-2xl font-black text-[#2e2c92] uppercase tracking-wider">
              {{ purchase.supplier?.user?.name || 'OkayERP' }}
            </div>
            <p class="text-sm text-gray-500 mt-1">Purchase Inward System</p>
          </div>
          <div class="text-left md:text-right">
            <h2 class="text-2xl font-bold text-gray-900">PURCHASE BILL</h2>
            <p class="text-sm text-gray-500 mt-1">Purchase ID: <span class="font-semibold text-gray-800">#{{ purchase.id }}</span></p>
            <p v-if="purchase.invoice_no" class="text-sm text-gray-500">Supplier Invoice #: <span class="font-semibold text-gray-800">{{ purchase.invoice_no }}</span></p>
            <p class="text-sm text-gray-500">Date: <span class="font-semibold text-gray-800">{{ purchase.purchase_date ? formatDate(purchase.purchase_date) : formatDate(purchase.created_at) }}</span></p>
          </div>
        </div>

        <!-- Addresses / Billing info -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
          <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Ship To / Buyer</h3>
            <div class="text-sm text-gray-700 space-y-1">
              <p class="font-bold text-gray-900 text-base">{{ purchase.supplier?.user?.name || 'Your Business Name' }}</p>
              <p v-if="purchase.supplier?.user?.address" class="whitespace-pre-line text-gray-600">{{ purchase.supplier?.user?.address }}</p>
              <p v-if="purchase.supplier?.user?.phone" class="text-gray-600"><span class="font-medium text-gray-900">Phone:</span> {{ purchase.supplier?.user?.phone }}</p>
              <p v-if="purchase.supplier?.user?.email" class="text-gray-600"><span class="font-medium text-gray-900">Email:</span> {{ purchase.supplier?.user?.email }}</p>
            </div>
          </div>

          <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Supplier / Seller</h3>
            <div class="text-sm text-gray-700 space-y-1">
              <p class="font-bold text-gray-900 text-base">{{ purchase.supplier?.name || 'N/A' }}</p>
              <p v-if="purchase.supplier?.address" class="whitespace-pre-line text-gray-600">{{ purchase.supplier?.address }}</p>
              <p v-if="purchase.supplier?.phone" class="text-gray-600"><span class="font-medium text-gray-900">Phone:</span> {{ purchase.supplier?.phone }}</p>
              <p v-if="purchase.supplier?.email" class="text-gray-600"><span class="font-medium text-gray-900">Email:</span> {{ purchase.supplier?.email }}</p>
            </div>
          </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto mb-8 border border-gray-200 rounded-xl">
          <table class="w-full text-sm text-left text-gray-600">
            <thead class="bg-[#f8fafc] text-gray-700 border-b border-gray-200">
              <tr>
                <th scope="col" class="px-6 py-4 font-bold">Product</th>
                <th scope="col" class="px-6 py-4 font-bold text-center">Unit</th>
                <th scope="col" class="px-6 py-4 font-bold text-center">Qty</th>
                <th scope="col" class="px-6 py-4 font-bold text-right">Price</th>
                <th scope="col" class="px-6 py-4 font-bold text-right">Base Amount</th>
                <th scope="col" class="px-6 py-4 font-bold text-right">Total</th>
                <th v-if="purchase.accepted" scope="col" class="px-6 py-4 font-bold text-right">GST %</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in purchase.items" :key="item.id" class="hover:bg-gray-50/50">
                <td class="px-6 py-4 font-semibold text-gray-900">
                  {{ item.product?.name || 'N/A' }}
                </td>
                <td class="px-6 py-4 text-center text-gray-500">
                  {{ item.unit_type }}
                </td>
                <td class="px-6 py-4 text-center font-medium text-gray-800">
                  {{ item.quantity }}
                </td>
                <td class="px-6 py-4 text-right text-gray-600 font-medium">
                  ₹{{ parseFloat(item.price).toFixed(2) }}
                </td>
                <td class="px-6 py-4 text-right text-gray-600">
                  ₹{{ parseFloat(item.base_price).toFixed(2) }}
                </td>
                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                  ₹{{ (parseFloat(item.price) * parseFloat(item.quantity)).toFixed(2) }}
                </td>
                <td v-if="purchase.accepted" class="px-6 py-4 text-right text-indigo-600 font-medium">
                  {{ (parseFloat(item.sgst) + parseFloat(item.cgst)).toFixed(2) }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals & Summary section -->
        <div class="flex flex-col md:flex-row justify-between items-start gap-8">
          <!-- Left side: Payment Info -->
          <div class="w-full md:w-1/2 space-y-4">
            <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
              <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Payment details</h4>
              <div class="space-y-1.5 text-sm">
                <p class="text-gray-600"><span class="font-medium text-gray-800">Method:</span> {{ purchase.payment_method || 'N/A' }}</p>
                <div class="flex items-center gap-2">
                  <span class="text-gray-600 font-medium">Status:</span>
                  <span 
                    class="px-2 py-0.5 text-xs font-semibold rounded"
                    :class="{
                      'bg-emerald-100 text-emerald-800': purchase.payment_status === 'Paid',
                      'bg-amber-100 text-amber-800': purchase.payment_status === 'Partial',
                      'bg-rose-100 text-rose-800': purchase.payment_status === 'Unpaid'
                    }"
                  >
                    {{ purchase.payment_status }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right side: Calculation Breakdown -->
          <div class="w-full md:w-5/12">
            <div class="divide-y divide-gray-100 text-sm">
              <div class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">Total Amount</span>
                <span class="text-gray-900 font-semibold">₹{{ parseFloat(purchase.total_amount).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">GST Amount</span>
                <span class="text-gray-900 font-semibold">₹{{ parseFloat(purchase.gst_amount).toFixed(2) }}</span>
              </div>
              <div v-if="purchase.transport_amount && parseFloat(purchase.transport_amount) > 0" class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">Transport Amount</span>
                <span class="text-gray-900 font-semibold">₹{{ parseFloat(purchase.transport_amount).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between py-3 text-base font-bold border-t border-gray-200">
                <span class="text-gray-900">Grand Total</span>
                <span class="text-[#2e2c92]">₹{{ parseFloat(purchase.grand_total).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">Paid</span>
                <span class="text-emerald-600 font-semibold">₹{{ parseFloat(purchase.paid).toFixed(2) }}</span>
              </div>
              <div v-if="parseFloat(allocatedPayment) > 0" class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">Advance Applied</span>
                <span class="text-emerald-600 font-semibold">₹{{ parseFloat(allocatedPayment).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between py-3 text-base font-bold bg-[#f8fafc] px-4 rounded-lg mt-2 border border-gray-100">
                <span class="text-gray-700">Balance Due</span>
                <span class="text-rose-600">₹{{ Math.max(0, parseFloat(purchase.grand_total) - parseFloat(purchase.paid) - parseFloat(allocatedPayment)).toFixed(2) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
@media print {
  /* Hide everything except the invoice container */
  body * {
    visibility: hidden;
  }
  #print-area, #print-area * {
    visibility: visible;
  }
  #print-area {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    box-shadow: none !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .no-print {
    display: none !important;
  }
}
</style>
