<script setup>
import { ref, watch, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const props = defineProps({
  purchases: {
    type: Array,
    required: true,
  },
});

const form = ref({
  purchase_id: "",
  return_date: new Date().toISOString().substring(0, 10),
  refund_method: "Cash",
  reason: "",
  due_deduction: 0,
  items: [],
});

const selectedPurchaseDetails = ref(null);
const isLoadingPurchase = ref(false);

// Watch purchase selection
watch(() => form.value.purchase_id, async (newVal) => {
  if (!newVal) {
    selectedPurchaseDetails.value = null;
    form.value.items = [];
    form.value.due_deduction = 0;
    return;
  }

  isLoadingPurchase.value = true;
  try {
    const response = await axios.get(`/purchase-return/purchase/${newVal}/details`);
    selectedPurchaseDetails.value = response.data;
    
    // Initialize returned quantities to 0
    form.value.items = response.data.items.map(item => ({
      product_id: item.product_id,
      product_name: item.product_name,
      price: parseFloat(item.price),
      cgst: parseFloat(item.cgst) || 0,
      sgst: parseFloat(item.sgst) || 0,
      purchased_qty: item.purchased_qty,
      returned_qty: item.returned_qty,
      available_qty: item.available_qty,
      quantity: 0, // returned qty input
    }));

    form.value.due_deduction = 0;
  } catch (error) {
    console.error("Error fetching purchase details:", error);
    toast.error("Failed to load purchase details.");
  } finally {
    isLoadingPurchase.value = false;
  }
});

// Computed values for refunds
const baseRefundTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    const qty = parseInt(item.quantity) || 0;
    return sum + (qty * item.price);
  }, 0);
});

const gstRefundTotal = computed(() => {
  if (!selectedPurchaseDetails.value || selectedPurchaseDetails.value.accepted != 1) {
    return 0;
  }

  return form.value.items.reduce((sum, item) => {
    const qty = parseInt(item.quantity) || 0;
    const gstRate = (item.sgst + item.cgst) / 100;
    return sum + (qty * item.price * gstRate);
  }, 0);
});

const grandRefundTotal = computed(() => {
  return baseRefundTotal.value + gstRefundTotal.value;
});

// Watch grandRefundTotal to suggest the maximum due deduction
watch(grandRefundTotal, (newVal) => {
  if (selectedPurchaseDetails.value) {
    const dueAmount = parseFloat(selectedPurchaseDetails.value.due_amount) || 0;
    const maxDeduct = Math.min(newVal, dueAmount);
    form.value.due_deduction = parseFloat(maxDeduct.toFixed(2));
  } else {
    form.value.due_deduction = 0;
  }
});

const netRefundCashToPay = computed(() => {
  return Math.max(0, grandRefundTotal.value - (parseFloat(form.value.due_deduction) || 0));
});

const submitReturn = async () => {
  const payloadItems = form.value.items.filter(item => item.quantity > 0);

  if (!form.value.purchase_id) {
    toast.error("Please select a purchase bill.");
    return;
  }

  if (payloadItems.length === 0) {
    toast.error("Please specify a returned quantity of at least 1 for one or more products.");
    return;
  }

  // Validate quantities do not exceed available limits
  for (const item of payloadItems) {
    if (item.quantity > item.available_qty) {
      toast.error(`Returned quantity for ${item.product_name} exceeds the maximum returnable limit of ${item.available_qty}.`);
      return;
    }
  }

  // Validate due deduction amount
  const dueAmount = parseFloat(selectedPurchaseDetails.value.due_amount) || 0;
  const maxDeduct = Math.min(grandRefundTotal.value, dueAmount);
  if (form.value.due_deduction < 0 || form.value.due_deduction > maxDeduct) {
    toast.error(`Due deduction amount must be between 0 and ₹ ${maxDeduct.toFixed(2)}.`);
    return;
  }

  try {
    const payload = {
      purchase_id: form.value.purchase_id,
      return_date: form.value.return_date,
      refund_method: form.value.refund_method,
      reason: form.value.reason,
      due_deduction: form.value.due_deduction,
      items: payloadItems.map(item => ({
        product_id: item.product_id,
        quantity: item.quantity,
      })),
    };

    const response = await axios.post('/purchase-return/store', payload);
    toast.success(response.data.message);
    if (response.data.invoice_url) {
      window.open(response.data.invoice_url, '_blank');
    }
    router.visit(route('purchase-return.index'));
  } catch (error) {
    const msg = error.response?.data?.message || "An error occurred while saving the return.";
    toast.error(msg);
  }
};
</script>

<template>
  <Head title="Record Purchase Return">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  </Head>

  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
      <div class="main-back-class">
        <a :href="route('purchase-return.index')">
          <i style="font-size: 14px;" class="bi bi-chevron-left"></i>
          <span style="margin-left: 5px;">Purchase Returns</span>
        </a>
      </div>
      
      <h2 class="text-2xl font-bold mb-4 text-[#292688]">Record Purchase Return</h2>

      <div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-black font-medium mb-2">Original Purchase Bill <span class="text-red-500">*</span></label>
            <vSelect
              v-model="form.purchase_id"
              :options="purchases"
              label="invoice_label"
              :reduce="p => p.id"
              placeholder="Search or select purchase bill"
              class="w-full text-black bg-white"
            />
          </div>
        </div>

        <div v-if="isLoadingPurchase" class="py-6 text-center text-gray-500 font-semibold">
          Loading purchase details...
        </div>

        <div v-if="selectedPurchaseDetails" class="mt-8 space-y-6">
          <div class="p-4 bg-slate-50 border rounded-lg text-black grid grid-cols-1 md:grid-cols-2 gap-4">
            <p><strong>Supplier Name:</strong> {{ selectedPurchaseDetails.supplier_name }}</p>
            <p><strong>Tax Scheme:</strong> {{ selectedPurchaseDetails.accepted == 1 ? 'GST Invoice' : 'Non-GST / Private Invoice' }}</p>
          </div>

          <h3 class="text-xl font-bold text-[#292688]">Items Purchased</h3>
          
          <!-- Desktop view: Table layout -->
          <table class="hidden md:table w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
            <thead class="bg-[#292688] text-white">
              <tr>
                <th class="px-4 py-2 text-left">Product</th>
                <th class="px-4 py-2 text-left">Qty</th>
                <th class="px-4 py-2 text-left">Cost (Excl. GST)</th>
                <th class="px-4 py-2 text-left w-32">Return Qty</th>
                <th class="px-4 py-2 text-left">Refund Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in form.items" :key="index">
                <td class="border-t px-4 py-3 font-semibold text-gray-800">{{ item.product_name }}</td>
                <td class="border-t px-4 py-3 font-bold text-indigo-600">{{ item.available_qty }}</td>
                <td class="border-t px-4 py-3 text-gray-600">₹ {{ item.price.toFixed(2) }}</td>
                <td class="border-t px-4 py-3">
                  <input
                    type="number"
                    v-model.number="item.quantity"
                    min="0"
                    :max="item.available_qty"
                    :disabled="item.available_qty === 0"
                    class="w-full px-3 py-1.5 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition disabled:bg-gray-100 disabled:text-gray-400"
                    placeholder="0"
                  />
                </td>
                <td class="border-t px-4 py-3 font-bold text-gray-700">
                  ₹ {{ ((item.quantity || 0) * item.price).toFixed(2) }}
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Mobile view: Stacked card layout -->
          <div class="md:hidden space-y-4">
            <div v-for="(item, index) in form.items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
              <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <span class="font-bold text-sm text-[#292688]">{{ item.product_name }}</span>
                <span class="text-xs bg-indigo-50 text-indigo-700 px-2.5 py-1 rounded-full font-semibold">
                  Cost: ₹ {{ item.price.toFixed(2) }}
                </span>
              </div>

              <div class="grid grid-cols-1 gap-2 text-center text-xs bg-white p-3 rounded-lg border border-gray-100 font-medium text-gray-500">
                <div>
                  <span class="block text-gray-400 mb-0.5 text-indigo-600 font-semibold">Available to Return</span>
                  <span class="text-indigo-700 font-bold text-sm">{{ item.available_qty }}</span>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4 items-center">
                <div>
                  <label class="block text-xs font-semibold text-gray-500 mb-1">Return Qty</label>
                  <input
                    type="number"
                    v-model.number="item.quantity"
                    min="0"
                    :max="item.available_qty"
                    :disabled="item.available_qty === 0"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm disabled:bg-gray-100 disabled:text-gray-400"
                    placeholder="0"
                  />
                </div>
                <div class="text-right">
                  <span class="block text-xs font-semibold text-gray-400">Refund Amount</span>
                  <span class="text-lg font-bold text-gray-800">
                    ₹ {{ ((item.quantity || 0) * item.price).toFixed(2) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Return Metadata -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t pt-6">
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Return Date <span class="text-red-500">*</span></label>
                <input
                  type="date"
                  v-model="form.return_date"
                  required
                  class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Refund Method <span class="text-red-500">*</span></label>
                <select
                  v-model="form.refund_method"
                  required
                  class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
                >
                  <option value="Cash">Cash</option>
                  <option value="Card">Card</option>
                  <option value="UPI">UPI</option>
                  <option value="Store Credit">Store Credit (Offsets Accounts Payable)</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Return</label>
                <textarea
                  v-model="form.reason"
                  rows="3"
                  class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
                  placeholder="e.g. Broken packaging, wrong shipment..."
                ></textarea>
              </div>
            </div>

            <!-- Refund Totals Panel -->
            <div class="bg-slate-50 p-6 rounded-xl border border-gray-100 flex flex-col justify-between">
              <h4 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4">Refund Summary</h4>
              <div class="space-y-4">
                <div class="flex justify-between">
                  <span class="text-gray-600">Base Refund Total:</span>
                  <span class="font-mono font-bold text-gray-800">₹ {{ baseRefundTotal.toFixed(2) }}</span>
                </div>
                <div v-if="selectedPurchaseDetails.accepted == 1" class="flex justify-between">
                  <span class="text-gray-600">GST Refund Total:</span>
                  <span class="font-mono font-bold text-gray-800">₹ {{ gstRefundTotal.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between border-t pt-3 font-semibold">
                  <span class="text-gray-800">Total Refund Value:</span>
                  <span class="font-mono text-indigo-700">₹ {{ grandRefundTotal.toFixed(2) }}</span>
                </div>
                
                <div class="border-t pt-3 space-y-2">
                  <label class="block text-sm font-semibold text-gray-700">Deduct from Bill Due (Max: ₹ {{ Math.min(grandRefundTotal, parseFloat(selectedPurchaseDetails.due_amount) || 0).toFixed(2) }})</label>
                  <input
                    type="number"
                    step="0.01"
                    v-model.number="form.due_deduction"
                    :min="0"
                    :max="Math.min(grandRefundTotal, parseFloat(selectedPurchaseDetails.due_amount) || 0)"
                    class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black font-mono font-bold"
                  />
                </div>

                <div class="flex justify-between border-t pt-3 font-semibold text-lg">
                  <span class="text-gray-800">Net Refund (Cash/UPI/Card):</span>
                  <span class="font-mono text-emerald-700">₹ {{ netRefundCashToPay.toFixed(2) }}</span>
                </div>
              </div>

              <div class="mt-6 flex justify-end">
                <button
                  @click="submitReturn"
                  class="w-full md:w-auto bg-[#2E2C92] hover:bg-[#1e1c6e] text-white px-6 py-3 rounded-xl font-bold shadow-md hover:shadow-lg transition-colors cursor-pointer"
                >
                  Process Return
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
