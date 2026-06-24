<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  return: {
    type: Object,
    required: true
  }
});

const returnData = props.return;

// Format dates nicely
const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
};
</script>

<template>
  <Head :title="`Credit Note #${returnData.return_no}`">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6 max-w-5xl mx-auto">
      <!-- Breadcrumb & Top Actions -->
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8 no-print">
        <div class="flex items-center gap-3">
          <a href="/sale-return" class="flex items-center justify-center w-10 h-10 rounded-lg bg-white shadow-sm border border-gray-200 text-gray-600 hover:text-red-700 hover:border-red-700 transition-colors duration-200">
            <i class="fa fa-arrow-left"></i>
          </a>
          <div>
            <span class="text-sm font-medium text-gray-500 uppercase tracking-wider">Sales Return</span>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">Credit Note Details</h1>
          </div>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <!-- Download PDF -->
          <a
            :href="`/sale-return/${returnData.id}/download-pdf`"
            target="_blank"
            class="flex items-center justify-center gap-2 px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-200 w-full sm:w-auto"
          >
            <i class="fa fa-file-pdf-o"></i> Download PDF
          </a>
        </div>
      </div>

      <!-- Credit Note Content Container -->
      <div id="print-area" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 md:p-12">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-gray-200 pb-8 mb-8 gap-4">
          <div>
            <div class="text-2xl font-black text-red-700 uppercase tracking-wider">
              {{ returnData.sale?.customer?.user?.name || 'OkayERP' }}
            </div>
            <p class="text-sm text-gray-500 mt-1">Return Management System</p>
          </div>
          <div class="text-left md:text-right">
            <h2 class="text-2xl font-bold text-red-700">CREDIT NOTE</h2>
            <p class="text-sm text-gray-500 mt-1">Return #: <span class="font-semibold text-gray-800">{{ returnData.return_no }}</span></p>
            <p class="text-sm text-gray-500">Date: <span class="font-semibold text-gray-800">{{ formatDate(returnData.return_date) }}</span></p>
            <p class="text-sm text-gray-500">Original Invoice #: <span class="font-semibold text-gray-800">#{{ returnData.sale_id }}</span></p>
          </div>
        </div>

        <!-- Addresses -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
          <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">From</h3>
            <div class="text-sm text-gray-700 space-y-1">
              <p class="font-bold text-gray-900 text-base">{{ returnData.sale?.customer?.user?.name || 'Your Business Name' }}</p>
              <p v-if="returnData.sale?.customer?.user?.address" class="whitespace-pre-line text-gray-600">{{ returnData.sale?.customer?.user?.address }}</p>
              <p v-if="returnData.sale?.customer?.user?.phone" class="text-gray-600"><span class="font-medium text-gray-900">Phone:</span> {{ returnData.sale?.customer?.user?.phone }}</p>
              <p v-if="returnData.sale?.customer?.user?.email" class="text-gray-600"><span class="font-medium text-gray-900">Email:</span> {{ returnData.sale?.customer?.user?.email }}</p>
            </div>
          </div>

          <div>
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Refund To</h3>
            <div class="text-sm text-gray-700 space-y-1">
              <p class="font-bold text-gray-900 text-base">{{ returnData.sale?.customer?.name || 'N/A' }}</p>
              <p v-if="returnData.sale?.customer?.address" class="whitespace-pre-line text-gray-600">{{ returnData.sale?.customer?.address }}</p>
              <p v-if="returnData.sale?.customer?.phone" class="text-gray-600"><span class="font-medium text-gray-900">Phone:</span> {{ returnData.sale?.customer?.phone }}</p>
              <p v-if="returnData.sale?.customer?.email" class="text-gray-600"><span class="font-medium text-gray-900">Email:</span> {{ returnData.sale?.customer?.email }}</p>
            </div>
          </div>
        </div>

        <!-- Items Table -->
        <div class="overflow-x-auto mb-8 border border-gray-200 rounded-xl">
          <table class="w-full text-sm text-left text-gray-600">
            <thead class="bg-red-50 text-red-950 border-b border-gray-200">
              <tr>
                <th scope="col" class="px-6 py-4 font-bold">Product</th>
                <th scope="col" class="px-6 py-4 font-bold text-center">Returned Qty</th>
                <th scope="col" class="px-6 py-4 font-bold text-right">Unit Price</th>
                <th scope="col" class="px-6 py-4 font-bold text-right">Total Refund</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="item in returnData.items" :key="item.id" class="hover:bg-gray-50/50">
                <td class="px-6 py-4 font-semibold text-gray-900">
                  {{ item.product?.name || 'N/A' }}
                </td>
                <td class="px-6 py-4 text-center font-medium text-gray-800">
                  {{ item.quantity }}
                </td>
                <td class="px-6 py-4 text-right text-gray-600 font-medium">
                  ₹{{ parseFloat(item.price).toFixed(2) }}
                </td>
                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                  ₹{{ (parseFloat(item.price) * parseFloat(item.quantity)).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals & Summary section -->
        <div class="flex flex-col md:flex-row justify-between items-start gap-8">
          <!-- Left side: Reason for return -->
          <div class="w-full md:w-1/2">
            <div v-if="returnData.reason" class="p-4 bg-gray-50 rounded-xl border border-gray-100">
              <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Reason for Return</h4>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ returnData.reason }}</p>
            </div>
            <div v-if="returnData.refund_method" class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
              <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Refund Method:</span>
              <span class="ml-2 text-sm font-semibold text-gray-800">{{ returnData.refund_method }}</span>
            </div>
          </div>

          <!-- Right side: Calculation Breakdown -->
          <div class="w-full md:w-5/12">
            <div class="divide-y divide-gray-100 text-sm">
              <div class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">Subtotal Refund</span>
                <span class="text-gray-900 font-semibold">₹{{ parseFloat(returnData.refund_amount).toFixed(2) }}</span>
              </div>
              <div v-if="parseFloat(returnData.gst_refund_amount) > 0" class="flex justify-between py-3">
                <span class="text-gray-500 font-medium">GST Refund</span>
                <span class="text-gray-900 font-semibold">₹{{ parseFloat(returnData.gst_refund_amount).toFixed(2) }}</span>
              </div>
              <div class="flex justify-between py-3 text-base font-bold border-t border-gray-200">
                <span class="text-gray-900">Total Refunded</span>
                <span class="text-red-700">₹{{ (parseFloat(returnData.refund_amount) + parseFloat(returnData.gst_refund_amount)).toFixed(2) }}</span>
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
