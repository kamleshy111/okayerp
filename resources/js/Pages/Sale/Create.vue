<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
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
  products: {
    type: Array,
    required: true,
  },
});

const customers = props.customers;
const products = props.products;

const customerSearchQuery = ref('');
const customerPurchasedItems = ref([]);
const isLoadingCustomerItems = ref(false);
const selectedProductToAdd = ref(null);
const productSearchQuery = ref('');

const filteredCustomers = computed(() => {
  const query = customerSearchQuery.value.trim().toLowerCase();
  const selected = customers.find(c => c.id == form.value.customer_id);

  if (!query) {
    return selected ? [selected] : [];
  }

  const filtered = customers.filter(customer => {
    const nameMatch = customer.name?.toLowerCase().includes(query);
    const emailMatch = customer.email?.toLowerCase().includes(query);
    const phoneMatch = customer.phone?.includes(query);
    const pinMatch = customer.pin_code?.includes(query);
    return nameMatch || emailMatch || phoneMatch || pinMatch;
  });

  if (selected && !filtered.some(c => c.id === selected.id)) {
    filtered.unshift(selected);
  }

  return filtered;
});

const form = ref({
    customer_id: "",
    estimate_id: "",
    grand_total: "",
    GstAmount: "",
    accepted: true,
    total_amount: "",
    paid: 0,
    discount: 0,
    payment_method: '',
    payment_status: "",
    sale_items: [],
});

const prefilledFromEstimateNo = ref('');

const clearEstimatePrefill = () => {
    form.value.estimate_id = '';
    prefilledFromEstimateNo.value = '';
};

onMounted(async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const estimateId = urlParams.get('estimate_id');
    if (estimateId) {
        try {
            const response = await axios.get(`/estimate/${estimateId}/get-json`);
            const estimate = response.data;
            if (estimate) {
                if (estimate.status === 'Invoiced') {
                    toast.error("This estimate has already been converted to a sale!");
                    return;
                }
                form.value.customer_id = estimate.customer_id;
                form.value.estimate_id = estimate.id;
                form.value.discount = parseFloat(estimate.discount) || 0;
                form.value.accepted = estimate.accepted == 1;

                // Map items
                form.value.sale_items = estimate.items.map(item => ({
                    product_id: item.product_id,
                    unit_type: item.unit_type || "",
                    sgst: item.sgst || 0,
                    cgst: item.cgst || 0,
                    quantity: item.quantity,
                    price: item.price,
                    baseAmount: item.base_price,
                }));

                prefilledFromEstimateNo.value = estimate.estimate_no;
                toast.success(`Loaded Estimate ${estimate.estimate_no}`);
            }
        } catch (error) {
            console.error("Error fetching estimate details:", error);
            toast.error("Failed to load estimate details.");
        }
    }
});

const selectedCustomer = ref(null);
const showPaymentModal = ref(false);
const customerData = ref(null);

const showCustomerModal = ref(false);
const newCustomer = ref({
  name: '',
  phone: '',
  email: '',
  gst_number: '',
  address: '',
  city: '',
  district: '',
  state: '',
  country: '',
  pin_code: ''
});

const onEnterKey = (event) => {
  if (!form.value.customer_id && selectedCustomer.value === null) {
    openCustomerModalWithName(event.target.value);
  }
};

const openCustomerModalWithName = (name) => {
  newCustomer.value = {
    name: name || '',
    phone: '',
    email: '',
    gst_number: '',
    address: '',
    city: '',
    district: '',
    state: '',
    country: '',
    pin_code: ''
  };
  showCustomerModal.value = true;
};

const submitCustomer = async () => {
  try {
    if (!newCustomer.value.name) {
      toast.error("Customer name is required!");
      return;
    }
    if (!newCustomer.value.phone) {
      toast.error("Customer phone is required!");
      return;
    }
    if (!newCustomer.value.email) {
      toast.error("Customer email is required!");
      return;
    }

    const response = await axios.post('/customer/store', newCustomer.value);
    const createdCustomer = response.data;
    customers.push(createdCustomer);

    form.value.customer_id = createdCustomer.id;
    selectedCustomer.value = createdCustomer;
    showCustomerModal.value = false;

    newCustomer.value = {
      name: '',
      phone: '',
      email: '',
      gst_number: '',
      address: '',
      city: '',
      district: '',
      state: '',
      country: '',
      pin_code: ''
    };

    toast.success("Customer added successfully!");
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

// Watch Customer
watch(() => form.value.customer_id, async (newVal) => {
  customerPurchasedItems.value = [];
  selectedProductToAdd.value = null;
  form.value.sale_items = [];

  if (!newVal || !Array.isArray(customers)) {
    selectedCustomer.value = null;
    return;
  }
  selectedCustomer.value = customers.find(s => s.id == newVal);

  // Fetch customer purchased items
  isLoadingCustomerItems.value = true;
  try {
    const response = await axios.get(`/sale-return/customer/${newVal}/purchased-items`);
    customerPurchasedItems.value = response.data;
  } catch (error) {
    console.error("Error fetching customer purchased items:", error);
  } finally {
    isLoadingCustomerItems.value = false;
  }
});

const dropdownProducts = computed(() => {
  const purchasedIds = new Set(customerPurchasedItems.value.map(item => item.product_id));

  // Previously purchased products
  const purchased = products.filter(p => purchasedIds.has(p.id)).map(p => ({
    ...p,
    display_label: `⭐ ${p.name} (Previously Purchased)`,
    is_purchased: true,
  }));

  // Other products
  const others = products.filter(p => !purchasedIds.has(p.id)).map(p => ({
    ...p,
    display_label: p.name,
    is_purchased: false,
  }));

  return [...purchased, ...others];
});

const filteredDropdownProducts = computed(() => {
  const query = productSearchQuery.value.trim().toLowerCase();
  if (!query) {
    return [];
  }
  return dropdownProducts.value.filter(p =>
    p.name.toLowerCase().includes(query)
  );
});

// Auto-add selected product when chosen from dropdown
watch(selectedProductToAdd, (newVal) => {
  if (newVal) {
    addProductToList();
  }
});

const addProductToList = () => {
  if (!selectedProductToAdd.value) {
    return;
  }

  const prod = selectedProductToAdd.value;

  // Check if already added
  const exists = form.value.sale_items.some(item => item.product_id === prod.id);
  if (exists) {
    toast.error("This product is already in the sale list.");
    selectedProductToAdd.value = null;
    return;
  }

  form.value.sale_items.push({
    product_id: prod.id,
    product_name: prod.name,
    unit_type: prod.unit_type || "",
    sgst: parseFloat(prod.sgst) || 0,
    cgst: parseFloat(prod.cgst) || 0,
    quantity: 1,
    price: parseFloat(prod.price) || 0,
    baseAmount: parseFloat(prod.price) || 0,
  });

  selectedProductToAdd.value = null;
  toast.success(`Added ${prod.name} to sale list.`);
};

// Watch sale items
watch(() => form.value.sale_items, (newSaleItems) => {
  newSaleItems.forEach(item => {
    const product = products.find(product => product.id === item.product_id);
    if (product) {
      item.unit_type = product.unit_type;
      item.sgst = product.sgst;
      item.cgst = product.cgst;

      const quantity = parseFloat(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      const baseAmount = quantity * price;
      item.baseAmount = baseAmount;
    }
  });
}, { deep: true });

// Watcher to fetch supplier data when selected supplier changes
watch(
  () => selectedCustomer.value, // watch the entire object
  async (customer) => {
    const newId = customer?.id;

    if (newId) {
      try {
        const response = await axios.get(`/sale/payment/${newId}`);
        customerData.value = response.data;
        console.log('Fetched customer data:', customerData.value);
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

const removeRow = (index) => {
    form.value.sale_items.splice(index, 1);
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

    let  total = totalAmount.value + totalGST.value;
     total -= form.value.discount || 0;
    return total;

});

const paymentStatus = computed(() => {
  if (!customerData.value) return 'Unpaid';

  const paidNow = parseFloat(form.value.paid) || 0;
  const currentNet = paidNow - grandTotal.value;

  if (paidNow === 0) return 'Unpaid';
  else if (currentNet < 0) return 'Partial';
  else if (currentNet > 0) return 'Advance';
  else return 'Paid';
});

// Show modal first, then submit all
const openPaymentModal = () => {
    const { customer_id, sale_items } = form.value;

    if (!customer_id) {
        toast.error("Please select a customer.");
        return;
    }

    if (sale_items.length === 0) {
        toast.error("Please add at least one product.");
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

        const selectedProduct = products.find(p => p.id === item.product_id);
        if (!selectedProduct) {
            toast.error(`Item ${i + 1}: Product not found.`);
            return;
        }

        if (quantity > selectedProduct.stock_quantity) {
            toast.error(`Item ${i + 1}: Only ${selectedProduct.stock_quantity} quantity are available in stock.`);
            return;
        }

    }

    showPaymentModal.value = true;
};

//add 225/08/25

const finalBalance = computed(() => {
  if (!customerData.value) return null;

  const paidNow = parseFloat(form.value.paid) || 0;
  const currentNet = paidNow - grandTotal.value;

  if (currentNet > 0) {
    return { type: 'advance', amount: currentNet };
  } else if (currentNet < 0) {
    return { type: 'due', amount: Math.abs(currentNet) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

//end


const submitForm = async () => {
  try {
    const payload = {
      ...form.value,
      grand_total: grandTotal.value,
      total_amount: totalAmount.value,
      GstAmount: totalGST.value,
      payment_status: paymentStatus.value,
    };

    const response = await axios.post(`/sale/store`, payload);
    toast.success(response.data.message);
    showPaymentModal.value = false;

    console.log('response', response.data);
    const invoiceUrl = response.data.invoice_url;
    window.open(invoiceUrl, '_blank');

    // Reset form
    form.value = {
      customer_id: "",
      grand_total: "",
      GstAmount: "",
      accepted: true,
      total_amount: "",
      paid: 0,
      discount: 0,
      payment_method: '',
      payment_status: "",
      sale_items: [],
    };
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
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Sale</h2>

            <div v-if="prefilledFromEstimateNo" class="p-4 mb-6 bg-purple-50 border border-purple-200 text-purple-800 rounded-lg flex items-center justify-between shadow-sm">
                <div class="flex items-center gap-2">
                    <span class="text-lg">⚡</span>
                    <span>Prefilled from <strong>Estimate #{{ prefilledFromEstimateNo }}</strong>. Stock levels will be deducted upon saving this sale.</span>
                </div>
                <button @click="clearEstimatePrefill" class="text-purple-600 hover:text-purple-900 font-semibold text-xs bg-purple-100 px-2.5 py-1 rounded-md transition duration-150">
                    Clear Prefill
                </button>
            </div>
        <div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Select Customer <span class="text-red-500">*</span></label>
                    <vSelect
                        v-model="form.customer_id"
                        :options="filteredCustomers"
                        label="name"
                        :reduce="customer => customer.id"
                        placeholder="Search or select customer"
                        class="w-full text-black bg-white"
                        @keydown.enter="onEnterKey"
                        @search="(query) => customerSearchQuery = query"
                    >
                        <!-- Shown when there are no options at all -->
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500 flex items-center justify-between">
                                <span v-if="!customerSearchQuery">Type to search customer...</span>
                                <span v-else>No customers found.</span>
                                <button
                                    v-if="customerSearchQuery && customerSearchQuery.trim()"
                                    @click.stop="showCustomerModal = true"
                                    class="mt-2 block text-blue-600 hover:underline text-sm"
                                >
                                    ➕ Add New Customer
                                </button>
                            </div>
                        </template>
                    </vSelect>
                </div>

                <div v-if="form.customer_id" class="flex flex-col">
                    <label class="block text-black font-medium mb-2">Search Product <span class="text-red-500">*</span></label>
                    <div class="flex items-center gap-2">
                        <vSelect
                            v-model="selectedProductToAdd"
                            :options="filteredDropdownProducts"
                            label="display_label"
                            placeholder="Type to search products..."
                            class="w-full text-black bg-white"
                            :disabled="isLoadingCustomerItems"
                            @search="(query) => productSearchQuery = query"
                            :close-on-select="false"
                            :clear-search-on-select="false"
                        >
                            <template #option="option">
                                <div class="flex justify-between items-center w-full">
                                    <span>
                                        {{ option.is_purchased ? '' : '' }}{{ option.name }}
                                        <span class="text-gray-500 text-xs ml-2">[Stock: {{ option.stock_quantity }}]</span>
                                    </span>
                                    <span class="text-[#2E2C92] font-bold flex items-center justify-center bg-indigo-50 hover:bg-indigo-100 rounded-full w-6 h-6 shadow-sm">
                                        <i class="bi bi-plus-lg"></i>
                                    </span>
                                </div>
                            </template>
                            <template #no-options>
                                search product then select
                            </template>
                        </vSelect>
                    </div>
                    <p v-if="isLoadingCustomerItems" class="text-xs text-gray-500 mt-1">Loading customer's purchased items...</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-if="selectedCustomer" class="mt-4 p-4 border rounded bg-gray-100 text-black">
                <p><strong>Phone:</strong> {{ selectedCustomer.phone }}</p>
                <p><strong>Email:</strong> {{ selectedCustomer.email }}</p>
                <p><strong>Address:</strong> {{ selectedCustomer.address }}</p>
                </div>
            </div>

            <div v-if="form.customer_id" class="mt-8 space-y-6">
                <div v-if="form.sale_items.length > 0" class="space-y-6">
                    <h3 class="text-xl font-bold text-[#292688]">Sale Items</h3>
                    <!-- Desktop view: Table layout -->
                    <table class="hidden md:table w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
                        <thead class="bg-[#292688] text-white">
                            <tr>
                                <th class="px-4 py-2 text-left">Product</th>
                                <th class="px-4 py-2 text-left">GST</th>
                                <th class="px-4 py-2 text-left">Quantity</th>
                                <th class="px-4 py-2 text-left">Unit Type</th>
                                <th class="px-4 py-2 text-left">Price</th>
                                <th class="px-4 py-2 text-left">Net Amount</th>
                                <th class="px-4 py-2 text-center w-20">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in form.sale_items" :key="index">
                                <td class="border-t px-4 py-3 font-semibold text-gray-800">
                                    {{ item.product_name || products.find(p => p.id === item.product_id)?.name }}
                                </td>

                                <td class="border-t px-4 py-3">
                                    {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %
                                </td>
                                <td class="border-t px-4 py-3">
                                    <input type="number" name="quantity" v-model="item.quantity" required min="1"
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
                                <td class="border-t px-4 py-3 font-bold text-gray-700">
                                    ₹ {{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)).toFixed(2) }}
                                </td>

                                <td class="border-t px-4 py-2 text-center">
                                    <button @click="removeRow(index)" type="button"
                                        class="text-red-600 hover:text-red-800 transition"
                                        title="Remove Item">
                                        <i class="bi bi-trash-fill text-lg"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Mobile view: Card list of items -->
                    <div class="md:hidden space-y-4">
                        <div v-for="(item, index) in form.sale_items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-3">
                            <div class="flex justify-between items-start font-bold text-[#292688] text-base pb-2 border-b border-gray-100">
                                <div>{{ item.product_name || products.find(p => p.id === item.product_id)?.name }}</div>
                                <button @click="removeRow(index)" type="button" class="text-red-500 hover:text-red-700 transition">
                                    <i class="bi bi-trash-fill text-lg"></i>
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity</label>
                                        <input type="number" v-model="item.quantity" required min="1"
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
                                <div class="grid grid-cols-2 gap-4 text-xs font-semibold text-gray-600 bg-white p-3 rounded-lg border border-gray-100">
                                    <div>
                                        <span class="block text-gray-400 font-medium">GST Rate</span>
                                        <span class="text-gray-800 font-semibold">{{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %</span>
                                    </div>
                                    <div>
                                        <span class="block text-gray-400 font-medium">Unit Type</span>
                                        <span class="text-gray-800 font-semibold">{{ item.unit_type || 'N/A' }}</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center bg-indigo-50/50 p-2.5 rounded-lg border border-indigo-100/50 text-xs font-bold">
                                    <span class="text-gray-500">Net Amount</span>
                                    <span class="text-[#292688]">₹ {{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)).toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="p-6 bg-slate-50 border border-dashed border-gray-300 rounded-xl text-center text-gray-500">
                    <i class="bi bi-cart-x text-3xl mb-2 block"></i>
                    No items added to the sale list yet. Search and add items above.
                </div>
            </div>
        </div>

        <div class="flex justify-end mt-6">
            <button @click="openPaymentModal" class="w-full md:w-auto bg-[#2E2C92] hover:bg-[#1d1b6a] text-white px-6 py-3 rounded-xl font-semibold transition shadow-md hover:shadow-lg">
                Submit & Proceed to Payment
            </button>
        </div>
    </div>
    <!-- Payment Modal -->
    <div v-if="showPaymentModal"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;">
        <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-md my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#292688]">Payment Details</h2>
                <button @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa fa-close"></i>
                </button>
            </div>

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
                    <input type="number" v-model.number="form.paid"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" min="0" />
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

                <!-- <div v-if="finalBalance?.type === 'none'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Balance</span>
                    <span class="text-gray-600 font-bold">₹ 0.00 (Clear)</span>
                </div> -->

                <!-- Payment Status -->
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

    <!-- Add Customer Modal -->
    <div v-if="showCustomerModal"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 99999;"
         @click.self="showCustomerModal = false">
        <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-md my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#2E2C92]">Add New Customer</h2>
                <button @click="showCustomerModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa fa-close"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" v-model="newCustomer.name" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" v-model="newCustomer.phone" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" v-model="newCustomer.email" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">GST Number</label>
                    <input type="text" v-model="newCustomer.gst_number" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" placeholder="e.g. 22AAAAA1111A1Z1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea v-model="newCustomer.address" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" rows="2"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" v-model="newCustomer.city" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <input type="text" v-model="newCustomer.district" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" v-model="newCustomer.state" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" v-model="newCustomer.country" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PIN Code</label>
                        <input type="text" v-model="newCustomer.pin_code" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button
                    @click="showCustomerModal = false"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition cursor-pointer font-medium"
                >
                    Cancel
                </button>
                <button
                    @click="submitCustomer"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-md transition cursor-pointer font-medium"
                >
                    Save Customer
                </button>
            </div>
        </div>
    </div>
    </AuthenticatedLayout>
</template>
