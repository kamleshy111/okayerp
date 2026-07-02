<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

import AddProductModal from '@/Components/AddProductModal.vue';

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
  categories: {
    type: Array,
    default: () => [],
  },
  unitTypes: {
    type: Object,
    default: () => ({}),
  },
  gstRates: {
    type: Array,
    required: true,
  },
});

const customers = ref([...props.customers]);
const products = ref([]);

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
    sale_items: [{
            product_id: "",
            unit_type: "",
            sgst: "",
            cgst: "",
            quantity: "",
            price: "",
            baseAmount: "",
            gst_rate_id: "",
            last_product_id: "",
    }],
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

                // Step 1: Load customer into customers array so vSelect shows selection
                if (estimate.customer) {
                    const alreadyExists = customers.value.some(c => c.id == estimate.customer.id);
                    if (!alreadyExists) {
                        customers.value.unshift(estimate.customer);
                    }
                    selectedCustomer.value = estimate.customer;
                }

                form.value.customer_id = estimate.customer_id;
                form.value.estimate_id = estimate.id;
                form.value.discount = parseFloat(estimate.discount) || 0;
                form.value.accepted = estimate.accepted == 1;

                // Step 2: Load products from estimate.items.product relation into registry and products array
                estimate.items.forEach(item => {
                    if (item.product) {
                        productRegistry.value[item.product.id] = item.product;
                        const alreadyInProducts = products.value.some(p => p.id === item.product.id);
                        if (!alreadyInProducts) {
                            products.value.push(item.product);
                        }
                    }
                });

                // Step 3: Determine interstate status based on loaded customer
                const customerState = estimate.customer?.state?.trim().toLowerCase() || '';
                const storeStateVal = storeState.value.trim().toLowerCase();
                const isInterstateNow = customerState && storeStateVal !== customerState;

                // Step 4: Map items with correct GST rate using interstate knowledge
                form.value.sale_items = estimate.items.map(item => {
                    const sgst = parseFloat(item.sgst) || 0;
                    const cgst = parseFloat(item.cgst) || 0;
                    const totalProductRate = sgst + cgst;

                    // Filter rates based on interstate status
                    const applicableRates = isInterstateNow
                        ? props.gstRates.filter(r => r.name.toLowerCase().includes('igst'))
                        : props.gstRates.filter(r => !r.name.toLowerCase().includes('igst'));

                    const matchedRate = applicableRates.find(r => parseFloat(r.rate) === totalProductRate)
                        || props.gstRates.find(r => parseFloat(r.rate) === totalProductRate);

                    let computedCgst = cgst;
                    let computedSgst = sgst;
                    if (matchedRate && parseFloat(matchedRate.igst) > 0) {
                        computedCgst = parseFloat(matchedRate.igst) / 2;
                        computedSgst = parseFloat(matchedRate.igst) / 2;
                    } else if (matchedRate) {
                        computedCgst = parseFloat(matchedRate.cgst) || cgst;
                        computedSgst = parseFloat(matchedRate.sgst) || sgst;
                    }

                    return {
                        product_id: item.product_id,
                        unit_type: item.unit_type || "",
                        sgst: computedSgst,
                        cgst: computedCgst,
                        quantity: item.quantity,
                        price: item.price,
                        baseAmount: item.base_price,
                        gst_rate_id: matchedRate ? matchedRate.id : (props.gstRates[0]?.id || ""),
                        last_product_id: item.product_id,
                    };
                });

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
    // if (!newCustomer.value.phone) {
    //   toast.error("Customer phone is required!");
    //   return;
    // }
    // if (!newCustomer.value.email) {
    //   toast.error("Customer email is required!");
    //   return;
    // }

    const response = await axios.post('/customer/store', newCustomer.value);
    const createdCustomer = response.data;
    customers.value.push(createdCustomer);

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
watch(() => form.value.customer_id, (newVal) => {
  if (!newVal || !Array.isArray(customers.value)) {
    selectedCustomer.value = null;
    return;
  }
  selectedCustomer.value = customers.value.find(s => s.id == newVal);
});

const page = usePage();
const storeState = computed(() => page.props.auth?.user?.state || '');

const isInterstate = computed(() => {
  if (!selectedCustomer.value || !selectedCustomer.value.state) return false;
  return storeState.value.trim().toLowerCase() !== selectedCustomer.value.state.trim().toLowerCase();
});

const filteredGstRates = computed(() => {
  if (isInterstate.value) {
    return props.gstRates.filter(r => r.name.toLowerCase().includes('igst'));
  } else {
    return props.gstRates.filter(r => !r.name.toLowerCase().includes('igst'));
  }
});

// Watch isInterstate to update selected GST rates when customer changes
watch(isInterstate, (newVal) => {
  form.value.sale_items.forEach(item => {
    if (!item.gst_rate_id) return;
    const currentRate = props.gstRates.find(r => r.id === item.gst_rate_id);
    if (currentRate) {
      const targetRate = props.gstRates.find(r =>
        parseFloat(r.rate) === parseFloat(currentRate.rate) &&
        (newVal ? r.name.toLowerCase().includes('igst') : !r.name.toLowerCase().includes('igst'))
      );
      if (targetRate) {
        item.gst_rate_id = targetRate.id;
        if (parseFloat(targetRate.igst) > 0) {
          item.cgst = parseFloat(targetRate.igst) / 2;
          item.sgst = parseFloat(targetRate.igst) / 2;
        } else {
          item.cgst = parseFloat(targetRate.cgst) || 0;
          item.sgst = parseFloat(targetRate.sgst) || 0;
        }
      }
    }
  });
});

const hasGstSelected = computed(() => {
  return form.value.sale_items.some(item => !!item.gst_rate_id);
});

// Watch sale items
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
        const matchedRate = filteredGstRates.value.find(r => parseFloat(r.rate) === totalProductRate);
        if (matchedRate) {
          item.gst_rate_id = matchedRate.id;
          if (parseFloat(matchedRate.igst) > 0) {
            item.cgst = parseFloat(matchedRate.igst) / 2;
            item.sgst = parseFloat(matchedRate.igst) / 2;
          } else {
            item.cgst = parseFloat(matchedRate.cgst) || 0;
            item.sgst = parseFloat(matchedRate.sgst) || 0;
          }
        } else {
          const defaultRate = filteredGstRates.value[0];
          item.gst_rate_id = defaultRate?.id || "";
          if (defaultRate && parseFloat(defaultRate.igst) > 0) {
            item.cgst = parseFloat(defaultRate.igst) / 2;
            item.sgst = parseFloat(defaultRate.igst) / 2;
          } else {
            item.cgst = defaultRate ? parseFloat(defaultRate.cgst) || 0 : 0;
            item.sgst = defaultRate ? parseFloat(defaultRate.sgst) || 0 : 0;
          }
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
    if (parseFloat(selectedRate.igst) > 0) {
      item.cgst = parseFloat(selectedRate.igst) / 2;
      item.sgst = parseFloat(selectedRate.igst) / 2;
    } else {
      item.cgst = parseFloat(selectedRate.cgst) || 0;
      item.sgst = parseFloat(selectedRate.sgst) || 0;
    }
  }
};

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

        const selectedProduct = products.value.find(p => p.id === item.product_id);
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
      sale_items: [{
        product_id: "",
        unit_type: "",
        sgst: "",
        cgst: "",
        quantity: "",
        price: "",
        baseAmount: "",
      }],
    };
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

// Add Product Modal State
const showProductModal = ref(false);
const activeProductRowIndex = ref(null);

const openProductModal = (rowIndex, search) => {
  activeProductRowIndex.value = rowIndex;
  productSearchQuery.value = search || '';
  showProductModal.value = true;
};

const handleProductSuccess = (createdProduct) => {
  productRegistry.value[createdProduct.id] = createdProduct;
  products.value.push(createdProduct);
  if (activeProductRowIndex.value !== null) {
    form.value.sale_items[activeProductRowIndex.value].product_id = createdProduct.id;
  }
  showProductModal.value = false;
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
                    <label class="block text-black font-medium mb-2">Customer <span class="text-red-500">*</span></label>
                    <vSelect
                        v-model="form.customer_id"
                        :options="customers"
                        label="name"
                        :reduce="customer => customer.id"
                        placeholder="Search or select customer"
                        class="w-full text-black bg-white"
                        @keydown.enter="onEnterKey"
                        @search="onCustomerSearch"
                    >
                        <!-- Shown when there are no options at all -->
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500">
                                <span v-if="!customerSearchQuery">Type to search customer name...</span>
                                <span v-else class="flex justify-between"><span>No customers found.</span>
                                    <button
                                        @click.stop="showCustomerModal = true"
                                        class="mt-2 block text-blue-600 hover:underline text-sm"
                                    >
                                        ➕ Add New Customer
                                    </button>
                                </span>
                            </div>
                        </template>
                    </vSelect>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-if="selectedCustomer" class="mt-4 p-4 border rounded bg-gray-100 text-black">
                <p><strong>Phone:</strong> {{ selectedCustomer.phone }}</p>
                <p><strong>Email:</strong> {{ selectedCustomer.email }}</p>
                <p><strong>Address:</strong> {{ selectedCustomer.address }}</p>
                </div>
            </div>

            <div class="mt-6"> <h3 class="text-2xl font-bold mb-4 text-[#292688]">Sale Items</h3> </div>

            <!-- Desktop view: Table layout -->
            <table class="hidden md:table w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
                <thead class="bg-[#292688] text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Product <span class="text-red-500">*</span></th>
                        <th class="px-4 py-2 text-left">GST</th>
                        <th class="px-4 py-2 text-left">Quantity <span class="text-red-500">*</span></th>
                        <th class="px-4 py-2 text-left">Unit Type</th>
                        <th class="px-4 py-2 text-left">Price <span class="text-red-500">*</span></th>
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
                            >
                                <template #no-options="{ search, searching, loading }">
                                    <div class="px-3 py-2 text-gray-500">
                                        <span v-if="!search">Type to search product...</span>
                                        <span v-else>No products found.</span>
                                        <button
                                            @click.stop="openProductModal(index, search)"
                                            class="mt-2 block text-blue-600 hover:underline text-sm"
                                        >
                                            ➕ Add New Product
                                        </button>
                                    </div>
                                </template>
                            </vSelect>
                        </td>
                        <td class="border-t px-4 py-3 min-w-[140px]">
                            <select
                                v-model="item.gst_rate_id"
                                @change="onGstRateChange(item)"
                                class="w-full border border-gray-300 px-2 py-1.5 rounded-md focus:ring-2 focus:ring-[#292688]"
                            >
                                <option value="" disabled>Select GST</option>
                                <option v-for="rate in filteredGstRates" :key="rate.id" :value="rate.id">
                                    {{ rate.name }}
                                </option>
                            </select>
                        </td>
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
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Product <span class="text-red-500">*</span></label>
                            <vSelect
                                v-model="item.product_id"
                                :options="products"
                                label="name"
                                :reduce="product => product.id"
                                placeholder="Search or select product"
                                class="w-full text-black bg-white"
                                @search="onProductSearch"
                            >
                                <template #no-options="{ search, searching, loading }">
                                    <div class="px-3 py-2 text-gray-500">
                                        <span v-if="!search">Type to search product...</span>
                                        <span v-else>No products found.</span>
                                        <button
                                            @click.stop="openProductModal(index, search)"
                                            class="mt-2 block text-blue-600 hover:underline text-sm"
                                        >
                                            ➕ Add New Product
                                        </button>
                                    </div>
                                </template>
                            </vSelect>
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
                                    <option v-for="rate in filteredGstRates" :key="rate.id" :value="rate.id">
                                        {{ rate.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity <span class="text-red-500">*</span></label>
                                <input type="number" v-model="item.quantity" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Qty" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price <span class="text-red-500">*</span></label>
                                <input type="number" v-model="item.price" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Price" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-white p-3 rounded-lg border border-gray-100 text-xs font-medium text-gray-500">
                            <div>
                                <span class="block text-gray-400">GST</span>
                                <span class="text-gray-800 font-semibold">
                                    <template v-if="item.gst_rate_id">
                                        {{ (parseFloat(item.cgst) || 0) + (parseFloat(item.sgst) || 0) }}%
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" v-model="newCustomer.phone" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" v-model="newCustomer.email" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
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
    <!-- Add Product Modal -->
    <AddProductModal
        :show="showProductModal"
        :initialName="productSearchQuery"
        :categories="categories"
        :unitTypes="unitTypes"
        @close="showProductModal = false"
        @success="handleProductSuccess"
    />
    </AuthenticatedLayout>
</template>
