<script setup>
import { ref, watch, computed, watchEffect  } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";


const props = defineProps({
  customers: {
    type: Array,
    required: false,
    default: () => [],
  },
  products: {
    type: Array,
    required: true,
  },
  sales: {
    type: Object,
    required: true,
  },
  productItems: {
    type: Array,
    required: true,
  },
  allocatedPayment: {
    type: [Number, String],
    default: 0,
  },
  gstRates: {
    type: Array,
    required: true,
  },
});

const customers = ref([...props.customers]);
const products = ref([...props.products]);
const productItems = props.productItems;
const sales = props.sales;

// Registry to store all products we have seen/loaded so far
const productRegistry = ref({});

// Initialize registry with initial products (from props)
if (props.products) {
  props.products.forEach(p => {
    productRegistry.value[p.id] = p;
  });
}

const productSearchQuery = ref('');
const onProductSearch = async (search, loading) => {
  productSearchQuery.value = search;
  if (!search.trim()) {
    let initialList = [];
    form.value.sale_items.forEach(item => {
      if (item.product_id && productRegistry.value[item.product_id]) {
        const alreadyInList = initialList.some(p => p.id === item.product_id);
        if (!alreadyInList) {
          initialList.push(productRegistry.value[item.product_id]);
        }
      }
    });
    products.value = initialList;
    return;
  }
  try {
    const response = await axios.get(`/product/search?query=${encodeURIComponent(search)}`);
    response.data.forEach(p => {
      productRegistry.value[p.id] = p;
    });

    let results = response.data;
    form.value.sale_items.forEach(item => {
      if (item.product_id && productRegistry.value[item.product_id]) {
        const alreadyInResults = results.some(p => p.id === item.product_id);
        if (!alreadyInResults) {
          results.push(productRegistry.value[item.product_id]);
        }
      }
    });
    products.value = results;
  } catch (error) {
    console.error("Error fetching products:", error);
  }
};

const customerSearchQuery = ref("");
const onCustomerSearch = async (search, loading) => {
  customerSearchQuery.value = search;
  if (!search.trim()) {
    const selectedId = form.value?.customer_id;
    if (selectedId) {
      const selected = selectedCustomer.value || customers.value.find(c => c.id == selectedId);
      if (selected) {
        customers.value = [selected];
        return;
      }
    }
    customers.value = [...props.customers];
    return;
  }
  try {
    const response = await axios.get(`/customer/search?query=${encodeURIComponent(search)}`);
    customers.value = response.data;
    
    // Ensure selected customer is always in options list
    const selected = selectedCustomer.value || props.customers.find(c => c.id == form.value.customer_id) || customers.value.find(c => c.id == form.value.customer_id);
    if (selected && !customers.value.some(c => c.id == selected.id)) {
      customers.value.unshift(selected);
    }
  } catch (error) {
    console.error("Error fetching customers:", error);
  }
};


const form = ref({
    id: sales.id,
    customer_id: sales.customer_id,
    grand_total: "",
    GstAmount: "",
    accepted: sales.accepted  === 1,
    paid: sales.paid,
    payment_method: sales.payment_method,
    discount: sales.discount || 0,
    sale_items: productItems.map(item => {
                    const sgst = parseFloat(item.sgst) || 0;
                    const cgst = parseFloat(item.cgst) || 0;
                    const totalProductRate = sgst + cgst;
                    const matchedRate = props.gstRates.find(r => parseFloat(r.rate) === totalProductRate);
                    return {
                        product_id: item.product_id,
                        quantity: item.quantity,
                        price: item.price,
                        unit_type: item.unit_type,
                        sgst: sgst,
                        cgst: cgst,
                        gst_rate_id: matchedRate ? matchedRate.id : (props.gstRates[0]?.id || ""),
                        last_product_id: item.product_id,
                    };
    }),
});

const selectedCustomer = ref(null);
const showPaymentModal = ref(false);

watchEffect(() => {
  if (form.value.customer_id && customers.value.length > 0) {
    selectedCustomer.value = customers.value.find(s => s.id == form.value.customer_id) || null;
  }
});

const customerData = ref(null);

watch(
  () => selectedCustomer.value,
  async (customer) => {
    const newId = customer?.id;
    if (newId) {
      try {
        const response = await axios.get(`/sale/payment/${newId}`);
        customerData.value = response.data;
      } catch (error) {
        console.error('Error fetching customer data:', error);
        customerData.value = null;
      }
    } else {
      customerData.value = null;
    }
  },
  { immediate: true }
);

const page = usePage();
const storeState = computed(() => page.props.auth?.user?.state || '');

const isInterstate = computed(() => {
  if (!selectedCustomer.value || !selectedCustomer.value.state) return false;
  return storeState.value.trim().toLowerCase() !== selectedCustomer.value.state.trim().toLowerCase();
});

const hasGstSelected = computed(() => {
  return form.value.sale_items.some(item => !!item.gst_rate_id);
});

// Watch for product change in each row to update unit_type
watch(() => form.value.sale_items, (newSaleItems) => {
  newSaleItems.forEach(item => {
    const selectedProduct = products.value.find(product => product.id === item.product_id);
    if (selectedProduct) {
      if (!item.last_product_id || item.last_product_id !== item.product_id) {
        item.unit_type = selectedProduct.unit_type;
        item.sgst = selectedProduct.sgst;
        item.cgst = selectedProduct.cgst;
        item.last_product_id = item.product_id;

        // Auto-match gst_rate_id from product's default tax rates
        const totalProductRate = (parseFloat(selectedProduct.sgst) || 0) + (parseFloat(selectedProduct.cgst) || 0);
        const matchedRate = props.gstRates.find(r => parseFloat(r.rate) === totalProductRate);
        if (matchedRate) {
          item.gst_rate_id = matchedRate.id;
        } else {
          item.gst_rate_id = props.gstRates[0]?.id || "";
          item.cgst = 0;
          item.sgst = 0;
        }
      }

      const quantity = parseFloat(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      const baseAmount = quantity * price;
      item.baseAmount = baseAmount;
    }
  });
}, { deep: true });

const onGstRateChange = (item) => {
  const selectedRate = props.gstRates.find(r => r.id === item.gst_rate_id);
  if (selectedRate) {
    item.cgst = parseFloat(selectedRate.cgst) || 0;
    item.sgst = parseFloat(selectedRate.sgst) || 0;
  }
};

const addRow = () => {
    // Add a new row to the sale_items array
    form.value.sale_items.push({
        product_id: "",
        unit_type: "",
        quantity: "",
        price: "",
        sgst: 0,
        cgst: 0,
        gst_rate_id: "",
        last_product_id: ""
    });
};

const removeRow = (index) => {
    // Remove a specific row from sale_items array
    if (form.value.sale_items.length > 1) {
        form.value.sale_items.splice(index, 1);
    } else {
        toast.error("At least one row is required.");
    }
};

const totalGST = computed(() => {
    if (!form.value.accepted) return 0;
    return form.value.sale_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const gst = sgst + cgst;
        const baseAmount = quantity * price;
        return sum + (baseAmount * gst / 100);
    }, 0);
});

const totalAmount = computed(() => {
    return form.value.sale_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const baseAmount = quantity * price;
        return sum + baseAmount;

    }, 0);
});

const grandTotal = computed(() => {
    let total = totalAmount.value + totalGST.value;
    total -= form.value.discount || 0;
    return total;
});

const dueAmount = computed(() => {
  const paidValue = (parseFloat(form.value.paid) || 0) + (parseFloat(props.allocatedPayment) || 0);
  const difference = paidValue - grandTotal.value;

  if (difference > 0) {
    return { type: 'advance', amount: difference };
  } else if (difference < 0) {
    return { type: 'due', amount: Math.abs(difference) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const customerBalanceExcludingCurrentSale = computed(() => {
  if (!customerData.value) return 0;

  const currentAdvance = parseFloat(customerData.value.advance_amount) || 0;
  const currentDue = parseFloat(customerData.value.due_amount) || 0;
  const currentNet = currentAdvance - currentDue;

  const originalPaid = parseFloat(props.sales.paid) || 0;
  const originalGrandTotal = parseFloat(props.sales.grand_total) || 0;
  const originalNet = originalPaid - originalGrandTotal;

  return currentNet - originalNet;
});

const previousAdvance = computed(() => {
  const bal = customerBalanceExcludingCurrentSale.value;
  return bal > 0 ? bal : 0;
});

const previousDue = computed(() => {
  const bal = customerBalanceExcludingCurrentSale.value;
  return bal < 0 ? Math.abs(bal) : 0;
});

const finalBalance = computed(() => {
  if (!customerData.value) return null;

  const paidNow = parseFloat(form.value.paid) || 0;
  // Use allocatedPayment since this is Edit mode where payments might already be allocated
  const paidValue = paidNow + (parseFloat(props.allocatedPayment) || 0);
  const currentNet = paidValue - grandTotal.value;

  if (currentNet > 0) {
    return { type: 'advance', amount: currentNet };
  } else if (currentNet < 0) {
    return { type: 'due', amount: Math.abs(currentNet) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const advanceApplied = computed(() => {
  const prevAdvance = previousAdvance.value;
  const paidNow = parseFloat(form.value.paid) || 0;
  return Math.min(prevAdvance, Math.max(0, grandTotal.value - paidNow));
});

const dueReduced = computed(() => {
  const prevDue = previousDue.value;
  const paidNow = parseFloat(form.value.paid) || 0;
  return Math.min(prevDue, Math.max(0, paidNow - grandTotal.value));
});

const paymentStatus = computed(() => {
  const paidValue = (parseFloat(form.value.paid) || 0) + (parseFloat(props.allocatedPayment) || 0);
  const currentNet = paidValue - grandTotal.value;

  if (paidValue === 0) return 'Unpaid';
  else if (currentNet < 0) return 'Partial';
  else if (currentNet > 0) return 'Advance';
  else return 'Paid';
});


// Show modal first, then submit all
const openPaymentModal = () => {
    const { id, sale_items } = form.value;

    if (!id) {
        toast.error("Please select a customer.");
        return;
    }

    for (let i = 0; i < sale_items.length; i++) {
        const item = sale_items[i];
        const quantity = parseFloat(item.quantity);
        const price = parseFloat(item.price);

        if (!item.product_id) {
            toast.error(`Item ${i + 1}: Product is required.`);
            return;
        }

        if (isNaN(quantity) || quantity <= 0) {
            toast.error(`Item ${i + 1}: Quantity is required.`);
            return;
        }

        if (isNaN(price) || price <= 0) {
            toast.error(`Item ${i + 1}: Price is required.`);
            return;
        }
    }

    showPaymentModal.value = true;
};

// Submit the form data
const submitForm = async () => {
  try {

    const payload = {
      ...form.value,
      grand_total: grandTotal.value,
      total_amount: totalAmount.value,
      GstAmount: totalGST.value,
      payment_status: paymentStatus.value,
    };

    const response = await axios.post(`/sale/update/${form.value.id}`, payload);
    toast.success(response.data.message);
    showPaymentModal.value = false;

  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

</script>
<template>

    <Head title="Sale">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('sale')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Sale</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Update Sale</h2>
        <div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Customer</label>
                    <vSelect
                        v-model="form.customer_id"
                        :options="customers"
                        label="name"
                        :reduce="customer => customer.id"
                        placeholder="Search or select customer"
                        class="w-full text-black bg-white"
                        @search="onCustomerSearch"
                    >
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500">
                                <span v-if="!customerSearchQuery">Type to search customer...</span>
                                <span v-else>No customers found.</span>
                            </div>
                        </template>
                    </vSelect>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-if="selectedCustomer" class="mt-4 p-4 border rounded bg-gray-100 text-black">
                <p><strong>Name:</strong> {{ selectedCustomer.name }}</p>
                <p><strong>Phone:</strong> {{ selectedCustomer.phone }}</p>
                <p><strong>Address:</strong> {{ selectedCustomer.address }}</p>
                </div>
            </div>

            <div>
                <h3 class="text-2xl font-bold mb-4 text-[#292688]">Sale Items</h3>
            </div>

            <!-- Desktop view: Table layout -->
            <table class="hidden md:table w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
                <thead class="bg-[#292688] text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Product</th>
                        <th class="px-4 py-2 text-left">GST</th>
                        <template v-if="hasGstSelected">
                            <th v-if="isInterstate" class="px-4 py-2 text-left">IGST</th>
                            <template v-else>
                                <th class="px-4 py-2 text-left">CGST</th>
                                <th class="px-4 py-2 text-left">SGST</th>
                            </template>
                        </template>
                        <th class="px-4 py-2 text-left">Quantity</th>
                        <th class="px-4 py-2 text-left">Unit Type</th>
                        <th class="px-4 py-2 text-left">Price</th>
                        <th class="px-4 py-2 text-left">Net Amount</th>
                        <th class="px-4 py-2 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in form.sale_items" :key="index">
                        <td class="border-t px-4 py-3 min-w-[220px]">
                            <vSelect
                                v-model="item.product_id"
                                :options="products"
                                label="name"
                                :reduce="product => product.id"
                                placeholder="Search or select product"
                                class="w-full text-black bg-white"
                                append-to-body
                                @search="onProductSearch"
                            />
                        </td>

                        <td class="border-t px-4 py-3 min-w-[140px]">
                            <select
                                v-model="item.gst_rate_id"
                                @change="onGstRateChange(item)"
                                class="w-full border border-gray-300 px-2 py-1.5 rounded-md focus:ring-2 focus:ring-[#292688]"
                            >
                                <option value="" disabled>Select GST</option>
                                <option v-for="rate in gstRates" :key="rate.id" :value="rate.id">
                                    {{ rate.name }}
                                </option>
                            </select>
                        </td>
                        <template v-if="hasGstSelected">
                            <td v-if="isInterstate" class="border-t px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                <span v-if="item.gst_rate_id" style="font-size: 20px;">
                                    {{ (parseFloat(item.cgst) || 0) + (parseFloat(item.sgst) || 0) }} %
                                </span>
                                <span v-else>-</span>
                            </td>
                            <template v-else>
                                <td class="border-t px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                    <span v-if="item.gst_rate_id" style="font-size: 20px;">
                                        {{ item.cgst }} %
                                    </span>
                                    <span v-else>-</span>
                                </td>
                                <td class="border-t px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                    <span v-if="item.gst_rate_id" style="font-size: 20px;">
                                        {{ item.sgst }} %
                                    </span>
                                    <span v-else>-</span>
                                </td>
                            </template>
                        </template>
                        <td class="border-t px-4 py-3">
                            <input type="number" name="quantity" v-model="item.quantity" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Qty" />
                        </td>
                        <td class="border-t px-4 py-3">
                            {{ item.unit_type }}
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="number" name="price" v-model="item.price" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Price" />
                        </td>
                        <td class="border-t px-4 py-3">
                            ₹  {{ (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) }}
                        </td>

                        <td class="border-t px-4 py-2">
                            <button @click="removeRow(index)" type="button"
                                class="bg-red-600 text-white px-6 py-2 rounded-md shadow hover:bg-red-700 transition mr-2">
                                Remove
                            </button>

                            <button v-if="index === form.sale_items.length - 1" @click="addRow" type="button"
                                class="bg-green-600 text-white px-6 py-2 rounded-md shadow hover:bg-green-700 transition">
                                Add Items
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Mobile view: Card list of items -->
            <div class="md:hidden space-y-4">
                <div v-for="(item, index) in form.sale_items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                        <span class="font-bold text-sm text-[#292688]">Item #{{ index + 1 }}</span>
                        <button @click="removeRow(index)" type="button" class="text-red-600 hover:text-red-800 text-sm font-semibold flex items-center gap-1">
                            <i class="fa fa-trash"></i> Remove
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Product</label>
                            <vSelect
                                v-model="item.product_id"
                                :options="products"
                                label="name"
                                :reduce="product => product.id"
                                placeholder="Search or select product"
                                class="w-full text-black bg-white"
                                @search="onProductSearch"
                            />
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 mb-1">GST Rate</label>
                                <select
                                    v-model="item.gst_rate_id"
                                    @change="onGstRateChange(item)"
                                    class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm bg-white"
                                >
                                    <option value="" disabled>Select GST</option>
                                    <option v-for="rate in gstRates" :key="rate.id" :value="rate.id">
                                        {{ rate.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity</label>
                                <input type="number" v-model="item.quantity" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Qty" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price</label>
                                <input type="number" v-model="item.price" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Price" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-white p-3 rounded-lg border border-gray-100 text-xs font-medium text-gray-500">
                            <div>
                                <span class="block text-gray-400">GST Breakdown</span>
                                <span class="text-gray-800 font-semibold">
                                    <template v-if="item.gst_rate_id">
                                        <span v-if="isInterstate">IGST: {{ (parseFloat(item.cgst) || 0) + (parseFloat(item.sgst) || 0) }}%</span>
                                        <span v-else>CGST: {{ item.cgst }}% | SGST: {{ item.sgst }}%</span>
                                    </template>
                                    <template v-else>-</template>
                                </span>
                            </div>
                            <div>
                                <span class="block text-gray-400">Unit Type</span>
                                <span class="text-gray-800 font-semibold">{{ item.unit_type || 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="block text-gray-400">Net Amount</span>
                                <span class="text-gray-800 font-bold">₹ {{ (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button @click="addRow" type="button"
                    class="w-full flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl font-semibold transition shadow-sm">
                    <i class="fa fa-plus-circle"></i> Add Items
                </button>
            </div>

            <div class="flex justify-end mt-6">
                <button @click="openPaymentModal" class="w-full md:w-auto bg-[#2E2C92] hover:bg-[#1d1b6a] text-white px-6 py-3 rounded-xl font-semibold transition shadow-md hover:shadow-lg">
                    Submit & Proceed to Payment
                </button>
            </div>
        </div>
    </div>
    <!-- Payment Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-xl w-96">
            <h2 class="text-2xl font-bold mb-4">Payment Details</h2>

            <!-- Discount Amount -->
            <div class="flex justify-between items-center">
                <label class="text-gray-700 font-medium">Discount</label>
                <input type="number" v-model="form.discount"
                    class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                    placeholder="₹0.00" min="0" />
            </div>

            <div class="space-y-4 border-t pt-4">

                <div class="flex justify-between items-center">
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" v-model="form.accepted" class="form-checkbox h-5 w-5 text-[#292688]">
                        <span class="text-sm text-gray-700 font-semibold">Apply To GST</span>
                    </label>
                </div>

                <div v-if="form.accepted" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">GST</span>
                    <span class="text-gray-800 font-bold">₹ {{ totalGST.toFixed(2) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Total Net Amount</span>
                    <span class="text-gray-800 font-bold">₹ {{ totalAmount.toFixed(2) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Grand Total</span>
                    <span class="text-black font-bold text-lg">₹ {{ grandTotal.toFixed(2) }}</span>
                </div>

                <!-- Payment Method -->
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Payment Method</label>
                    <select v-model="form.payment_method"
                            class="w-40 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>

                <!-- Paid Amount -->
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Paid Amount</label>
                    <input type="number" v-model="form.paid"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" min="0" />
                </div>

                <!-- Allocated General Payments -->
                <div v-if="parseFloat(props.allocatedPayment) > 0" class="flex justify-between items-center text-sm text-gray-500">
                    <span>Customer Payments Applied</span>
                    <span class="font-medium text-[#292688]">₹ {{ props.allocatedPayment }}</span>
                </div>

                <!-- Final Balance after this payment -->
                <div v-if="finalBalance?.type === 'advance'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Advance Amount</span>
                    <span class="text-green-600 font-bold">₹ {{ typeof finalBalance.amount === 'number' ? finalBalance.amount.toFixed(2) : finalBalance.amount }}</span>
                </div>

                <div v-if="finalBalance?.type === 'due'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Due Amount</span>
                    <span class="text-red-600 font-bold">₹ {{ typeof finalBalance.amount === 'number' ? finalBalance.amount.toFixed(2) : finalBalance.amount }}</span>
                </div>

                <div v-if="finalBalance?.type === 'none'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Balance</span>
                    <span class="text-gray-600 font-bold">₹ 0.00 (Clear)</span>
                </div>

                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                    <span class="text-gray-700 font-semibold">Payment Status</span>
                    <span class="text-blue-600 font-bold">{{ paymentStatus }}</span>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-3 pt-2">
                    <button @click="showPaymentModal = false" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer">Cancel</button>
                    <button @click="submitForm" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer">Final Submit</button>
                </div>
            </div>
        </div>
    </div>
    </AuthenticatedLayout>
</template>
