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
  customers: {
    type: Array,
    required: true,
  },
});

const form = ref({
  return_date: new Date().toISOString().substring(0, 10),
  refund_method: "Cash",
  reason: "",
  customer_due_deduction: 0,
  due_deductions: {},
  items: [],
});

const customerTotalDue = ref(0);

const selectedCustomerId = ref(null);
const customerSearchQuery = ref("");

const filteredCustomers = computed(() => {
  const query = customerSearchQuery.value.trim().toLowerCase();
  const selectedCustomer = props.customers.find(c => c.id === selectedCustomerId.value);

  if (!query) {
    return selectedCustomer ? [selectedCustomer] : [];
  }

  const filtered = props.customers.filter(customer =>
    customer.name.toLowerCase().includes(query) ||
    (customer.phone && customer.phone.includes(query))
  );

  if (selectedCustomer && !filtered.some(c => c.id === selectedCustomer.id)) {
    filtered.unshift(selectedCustomer);
  }

  return filtered;
});

const purchasedItems = ref([]);
const isLoadingItems = ref(false);
const selectedItemToAdd = ref(null);
const itemSearchQuery = ref("");

// Filter purchased items only when search query is typed
const filteredPurchasedItems = computed(() => {
  const query = itemSearchQuery.value.trim().toLowerCase();
  if (!query) {
    return [];
  }
  return purchasedItems.value.filter(item =>
    item.product_name.toLowerCase().includes(query)
  );
});

// Watch customer selection to load purchased items
watch(selectedCustomerId, async (newVal) => {
  purchasedItems.value = [];
  selectedItemToAdd.value = null;
  form.value.items = [];
  customerTotalDue.value = 0;
  form.value.customer_due_deduction = 0;

  if (!newVal) {
    return;
  }

  isLoadingItems.value = true;
  try {
    const response = await axios.get(`/sale-return/customer/${newVal}/purchased-items`);
    purchasedItems.value = response.data.items.map(item => ({
      ...item,
      display_label: `${item.product_name} [Available: ${item.available_qty}]`,
    }));
    customerTotalDue.value = parseFloat(response.data.customer_total_due) || 0;
  } catch (error) {
    console.error("Error fetching purchased items:", error);
    toast.error("Failed to load customer purchased items.");
  } finally {
    isLoadingItems.value = false;
  }
});

// Auto-add selected item to list when chosen from dropdown
watch(selectedItemToAdd, (newVal) => {
  if (newVal) {
    addItemToList();
  }
});

const addItemToList = () => {
  if (!selectedItemToAdd.value) {
    return;
  }

  const item = selectedItemToAdd.value;

  // Check if already added
  const exists = form.value.items.some(
    existing => existing.sale_id === item.sale_id && existing.product_id === item.product_id
  );

  if (exists) {
    toast.error("This item is already in the return list.");
    selectedItemToAdd.value = null;
    return;
  }

  // Add item with default quantity 1 (or available_qty if smaller)
  form.value.items.push({
    ...item,
    quantity: Math.min(1, item.available_qty),
  });

  // Reset selection
  selectedItemToAdd.value = null;
  toast.success(`Added ${item.product_name} to return list.`);
};

const removeItemFromList = (index) => {
  form.value.items.splice(index, 1);
};

// Computed values for refunds
const baseRefundTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    const qty = parseInt(item.quantity) || 0;
    return sum + (qty * item.price);
  }, 0);
});

const gstRefundTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    if (item.accepted != 1) {
      return sum;
    }
    const qty = parseInt(item.quantity) || 0;
    const gstRate = (item.sgst + item.cgst) / 100;
    return sum + (qty * item.price * gstRate);
  }, 0);
});

const grandRefundTotal = computed(() => {
  return baseRefundTotal.value + gstRefundTotal.value;
});

const affectedSales = computed(() => {
  const salesMap = {};

  form.value.items.forEach(item => {
    if (!salesMap[item.sale_id]) {
      salesMap[item.sale_id] = {
        sale_id: item.sale_id,
        invoice_label: item.invoice_label,
        due_amount: parseFloat(item.sale_due_amount) || 0,
        accepted: item.accepted,
        base_refund: 0,
        gst_refund: 0,
      };
    }

    const qty = parseInt(item.quantity) || 0;
    const base = qty * item.price;
    salesMap[item.sale_id].base_refund += base;

    if (item.accepted == 1) {
      const gstRate = (item.sgst + item.cgst) / 100;
      salesMap[item.sale_id].gst_refund += base * gstRate;
    }
  });

  return Object.values(salesMap).map(sale => {
    const totalRefund = sale.base_refund + sale.gst_refund;
    return {
      ...sale,
      total_refund: totalRefund,
      max_deduction: Math.min(totalRefund, sale.due_amount),
    };
  });
});

const maxAllowedDeduction = computed(() => {
  return Math.min(grandRefundTotal.value, customerTotalDue.value);
});

const distributeDeduction = () => {
  let remaining = parseFloat(form.value.customer_due_deduction) || 0;

  const maxLimit = maxAllowedDeduction.value;
  if (remaining > maxLimit) {
    remaining = maxLimit;
    form.value.customer_due_deduction = parseFloat(maxLimit.toFixed(2));
  }
};

watch([grandRefundTotal, customerTotalDue], () => {
  const maxLimit = maxAllowedDeduction.value;
  form.value.customer_due_deduction = parseFloat(maxLimit.toFixed(2));
}, { immediate: true });

watch(() => form.value.customer_due_deduction, distributeDeduction);

const totalDueDeductions = computed(() => {
  return parseFloat(form.value.customer_due_deduction) || 0;
});

const netRefundCashToPay = computed(() => {
  return Math.max(0, grandRefundTotal.value - totalDueDeductions.value);
});

const submitReturn = async () => {
  const payloadItems = form.value.items.filter(item => item.quantity > 0);

  if (payloadItems.length === 0) {
    toast.error("Please add at least one item with a return quantity of 1 or more.");
    return;
  }

  // Validate quantities do not exceed available limits
  for (const item of payloadItems) {
    if (item.quantity > item.available_qty) {
      toast.error(`Returned quantity for ${item.product_name} exceeds the maximum returnable limit of ${item.available_qty}.`);
      return;
    }
  }

  const payload = {
    customer_id: selectedCustomerId.value,
    return_date: form.value.return_date,
    refund_method: form.value.refund_method,
    reason: form.value.reason,
    due_deduction: parseFloat(form.value.customer_due_deduction) || 0,
    items: payloadItems.map(item => ({
      sale_id: item.sale_id,
      product_id: item.product_id,
      quantity: item.quantity,
    })),
  };

  try {
    const response = await axios.post('/sale-return/store', payload);
    toast.success("Sales return processed successfully!");

    if (response.data.invoice_url) {
      window.open(response.data.invoice_url, '_blank');
    }

    router.visit(route('sale-return.index'));
  } catch (error) {
    const msg = error.response?.data?.message || "An error occurred while saving the return.";
    toast.error(msg);
  }
};
</script>

<template>
  <Head title="Sales Return">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  </Head>

  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
      <div class="main-back-class">
        <a :href="route('sale-return.index')">
          <i style="font-size: 14px;" class="bi bi-chevron-left"></i>
          <span style="margin-left: 5px;">Sales Returns</span>
        </a>
      </div>

      <h2 class="text-2xl font-bold mb-4 text-[#292688]">Sale Return</h2>

      <div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-black font-medium mb-2">Select Customer <span class="text-red-500">*</span></label>
            <vSelect
              v-model="selectedCustomerId"
              :options="filteredCustomers"
              label="name"
              :reduce="customer => customer.id"
              placeholder="Type to search customer..."
              class="w-full text-black bg-white"
              @search="(query) => customerSearchQuery = query"
            >
              <template #no-options>
                search customer then select
              </template>
            </vSelect>
          </div>

          <div v-if="selectedCustomerId" class="flex flex-col">
            <label class="block text-black font-medium mb-2">Search Purchased Item <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-2">
              <vSelect
                v-model="selectedItemToAdd"
                :options="filteredPurchasedItems"
                label="product_name"
                placeholder="Type to search purchased items..."
                class="w-full text-black bg-white"
                :disabled="isLoadingItems"
                @search="(query) => itemSearchQuery = query"
                :close-on-select="false"
                :clear-search-on-select="false"
              >
                <template #option="option">
                  <div class="flex justify-between items-center w-full">
                    <span>{{ option.product_name }} [Available: {{ option.available_qty }}]</span>
                    <span class="text-[#2E2C92] font-bold flex items-center justify-center bg-indigo-50 hover:bg-indigo-100 rounded-full w-6 h-6 shadow-sm"><i class="bi bi-plus-lg"></i></span>
                  </div>
                </template>
                <template #no-options>
                  search item then select
                </template>
              </vSelect>
            </div>
            <p v-if="isLoadingItems" class="text-xs text-gray-500 mt-1">Loading purchased items...</p>
            <p v-else-if="purchasedItems.length === 0" class="text-xs text-amber-600 mt-1">No returnable purchased items found for this customer.</p>
          </div>
        </div>

        <div v-if="selectedCustomerId" class="mt-8 space-y-6">

          <div v-if="form.items.length > 0" class="space-y-6">
            <h3 class="text-xl font-bold text-[#292688]">Items to Return</h3>
            <!-- Desktop view: Table layout -->
            <table class="hidden md:table w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
              <thead class="bg-[#292688] text-white">
                <tr>
                  <th class="px-4 py-2 text-left">Product</th>
                  <th class="px-4 py-2 text-left">QTY</th>
                  <th class="px-4 py-2 text-left">Price (Excl. GST)</th>
                  <th class="px-4 py-2 text-left w-32">Return Qty</th>
                  <th class="px-4 py-2 text-left">Refund Amount</th>
                  <th class="px-4 py-2 text-center w-20">Action</th>
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
                      min="1"
                      :max="item.available_qty"
                      class="w-full px-2 py-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-center font-bold text-black"
                      placeholder="1"
                    />
                  </td>
                  <td class="border-t px-4 py-3 font-bold text-gray-700">
                    ₹ {{ ((item.quantity || 0) * item.price).toFixed(2) }}
                  </td>
                  <td class="border-t px-4 py-3 text-center">
                    <button
                      type="button"
                      @click="removeItemFromList(index)"
                      class="text-red-600 hover:text-red-800 transition"
                      title="Remove Item"
                    >
                      <i class="bi bi-trash-fill text-lg"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Mobile view: Card list of items -->
            <div class="md:hidden space-y-4">
              <div v-for="(item, index) in form.items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-3">
                <div class="flex justify-between items-start font-bold text-[#292688] text-base pb-2 border-b border-gray-100">
                  <div>
                    <div>{{ item.product_name }}</div>
                  </div>
                  <button
                    type="button"
                    @click="removeItemFromList(index)"
                    class="text-red-500 hover:text-red-700 transition"
                  >
                    <i class="bi bi-trash-fill text-lg"></i>
                  </button>
                </div>

                <div class="bg-white p-3 rounded-lg border border-gray-100 text-xs font-semibold text-gray-600 text-center">
                  <span class="text-gray-400 font-medium">QTY:</span>
                  <span class="text-indigo-600 font-bold ml-1">{{ item.available_qty }}</span>
                </div>

                <div class="flex items-center justify-between gap-4 pt-2">
                  <div class="w-1/2">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Price (Excl. GST)</label>
                    <span class="font-bold text-gray-800 text-sm">₹ {{ item.price.toFixed(2) }}</span>
                  </div>
                  <div class="w-1/2">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Return Qty</label>
                    <input
                      type="number"
                      v-model.number="item.quantity"
                      min="1"
                      :max="item.available_qty"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm text-center text-black font-bold"
                      placeholder="1"
                    />
                  </div>
                </div>

                <div class="flex justify-between items-center bg-indigo-50/50 p-2.5 rounded-lg border border-indigo-100/50 text-xs font-bold">
                  <span class="text-gray-600">Refund Amount:</span>
                  <span class="text-[#292688] font-mono text-sm">₹ {{ ((item.quantity || 0) * item.price).toFixed(2) }}</span>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="p-6 bg-slate-50 border border-dashed border-gray-300 rounded-xl text-center text-gray-500">
            <i class="bi bi-cart-x text-3xl mb-2 block"></i>
            No items added to the return list yet. Search and add items above.
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
                  class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
                />
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Refund Method <span class="text-red-500">*</span></label>
                <select
                  v-model="form.refund_method"
                  required
                  class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
                >
                  <option value="Cash">Cash</option>
                  <option value="Card">Card</option>
                  <option value="UPI">UPI</option>
                  <option value="Store Credit">Store Credit (Offsets Accounts Receivable)</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Return</label>
                <textarea
                  v-model="form.reason"
                  rows="3"
                  class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
                  placeholder="e.g. Defective stock, client cancelled..."
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
                <div v-if="gstRefundTotal > 0" class="flex justify-between">
                  <span class="text-gray-600">GST Refund Total:</span>
                  <span class="font-mono font-bold text-gray-800">₹ {{ gstRefundTotal.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between border-t pt-3 font-semibold">
                  <span class="text-gray-800">Total Refund Value:</span>
                  <span class="font-mono text-indigo-700">₹ {{ grandRefundTotal.toFixed(2) }}</span>
                </div>

                <!-- Due Deductions for Customer Total Due -->
                <div v-if="affectedSales.length > 0" class="border-t pt-3 space-y-3">

                  <div class="space-y-1">
                    <label class="block text-xs text-gray-500">
                      Deduct from Customer Due (Max: ₹ {{ maxAllowedDeduction.toFixed(2) }})
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      v-model.number="form.customer_due_deduction"
                      :min="0"
                      :max="maxAllowedDeduction"
                      class="w-full border border-gray-300 px-3 py-1.5 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black font-mono font-bold text-sm"
                    />
                  </div>
                </div>

                <div class="flex justify-between border-t pt-3 font-semibold text-lg">
                  <span class="text-gray-800">Net Refund (Cash/UPI/Card):</span>
                  <span class="font-mono text-emerald-700">₹ {{ netRefundCashToPay.toFixed(2) }}</span>
                </div>
              </div>

              <div class="mt-6 flex justify-end">
                <button
                  @click="submitReturn"
                  class="w-full md:w-auto bg-[#2E2C92] hover:bg-[#1e1c6e] text-white px-6 py-3 rounded-xl font-bold shadow-md transition-colors"
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
