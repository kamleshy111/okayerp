<script setup>
import { ref, watch, computed, watchEffect, onMounted, onUnmounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const props = defineProps({
  suppliers: {
    type: Array,
    required: false,
    default: () => [],
  },
  products: {
    type: Array,
    required: true,
  },
  purchases: {
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

const suppliers = ref([...props.suppliers]);
const products = ref([...props.products]);
const productItems = props.productItems;
const purchases = props.purchases;

const page = usePage();
const storeState = computed(() => page.props.auth?.user?.state || '');

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
    form.value.purchase_items.forEach(item => {
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
    form.value.purchase_items.forEach(item => {
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

const supplierSearchQuery = ref("");
const onSupplierSearch = async (search, loading) => {
  supplierSearchQuery.value = search;
  if (!search.trim()) {
    suppliers.value = [...props.suppliers];
    return;
  }
  try {
    const response = await axios.get(`/supplier/search?query=${encodeURIComponent(search)}`);
    suppliers.value = response.data;

    // Ensure selected supplier is always in options list
    const selected = props.suppliers.find(s => s.id == form.value.supplier_id) || suppliers.value.find(s => s.id == form.value.supplier_id);
    if (selected && !suppliers.value.some(s => s.id == selected.id)) {
      suppliers.value.unshift(selected);
    }
  } catch (error) {
    console.error("Error fetching suppliers:", error);
  }
};

const selectedSupplier = ref(
  props.suppliers.find(s => s.id == purchases.supplier_id) || null
);

const isInterstate = computed(() => {
  if (!selectedSupplier.value || !selectedSupplier.value.state) return false;
  return storeState.value.trim().toLowerCase() !== selectedSupplier.value.state.trim().toLowerCase();
});

const filteredGstRates = computed(() => {
  if (isInterstate.value) {
    return props.gstRates.filter(r => r.name.toLowerCase().includes('igst'));
  } else {
    return props.gstRates.filter(r => !r.name.toLowerCase().includes('igst'));
  }
});

const form = ref({
    id: purchases.id,
    supplier_id: purchases.supplier_id,
    invoice_no: purchases.invoice_no || '',
    purchase_date: purchases.purchase_date || '',
    received_date: purchases.received_date || '',
    delivery_mode: purchases.delivery_mode || 'By Hand',
    delivery_person_name: purchases.delivery_person_name || '',
    delivery_person_phone: purchases.delivery_person_phone || '',
    vehicle_type: purchases.vehicle_type || '',
    vehicle_number: purchases.vehicle_number || '',
    transport: purchases.transport_amount,
    grand_total: "",
    GstAmount: "",
    accepted: purchases.accepted  === 1,
    paid: purchases.paid,
    payment_method: purchases.payment_method,
    purchase_items: productItems.map(item => {
        const totalRate = (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0);
        const matchedRate = props.gstRates.find(r =>
          parseFloat(r.rate) === totalRate &&
          (isInterstate.value ? r.name.toLowerCase().includes('igst') : !r.name.toLowerCase().includes('igst'))
        );
        const defaultRate = props.gstRates.find(r =>
          isInterstate.value ? r.name.toLowerCase().includes('igst') : !r.name.toLowerCase().includes('igst')
        );
        return {
            product_id: item.product_id,
            quantity: item.quantity,
            price: item.price,
            sale_price: item.sale_price || "",
            unit_type: item.unit_type,
            sgst: item.sgst,
            cgst: item.cgst,
            gst_rate_id: matchedRate ? matchedRate.id : (defaultRate?.id || ""),
            last_product_id: item.product_id,
        };
    }),
});

watchEffect(() => {
  if (form.value.supplier_id && suppliers.value.length > 0) {
    selectedSupplier.value = suppliers.value.find(s => s.id == form.value.supplier_id) || null;
  }
});

const supplierData = ref(null);

watch(
  () => selectedSupplier.value,
  async (supplier) => {
    const newId = supplier?.id;
    if (newId) {
      try {
        const response = await axios.get(`/purchase/payment/${newId}`);
        supplierData.value = response.data;
      } catch (error) {
        console.error('Error fetching supplier data:', error);
        supplierData.value = null;
      }
    } else {
      supplierData.value = null;
    }
  },
  { immediate: true }
);

// Watch isInterstate to update selected GST rates when supplier changes
watch(isInterstate, (newVal) => {
  form.value.purchase_items.forEach(item => {
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
  return form.value.purchase_items.some(item => !!item.gst_rate_id);
});

watch(hasGstSelected, (newVal) => {
  form.value.accepted = newVal;
});

// Watch for product change in each row to update unit_type
watch(() => form.value.purchase_items, (newSaleItems) => {
  newSaleItems.forEach(item => {
    const selectedProduct = productRegistry.value[item.product_id] || products.value.find(product => product.id === item.product_id);
    if (selectedProduct) {
      if (!item.last_product_id || item.last_product_id !== item.product_id) {
        item.unit_type = selectedProduct.unit_type;
        item.last_product_id = item.product_id;
        item.gst_rate_id = "";
        item.cgst = 0;
        item.sgst = 0;
        item.sale_price = selectedProduct.price || "";
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
  } else {
    item.cgst = 0;
    item.sgst = 0;
  }
};

const addRow = () => {
    // Add a new row to the purchase_items array
    form.value.purchase_items.push({
        product_id: "",
        unit_type: "",
        quantity: "",
        price: "",
        sale_price: "",
        sgst: 0,
        cgst: 0,
        gst_rate_id: "",
        last_product_id: ""
    });
};

const removeRow = (index) => {
    if (form.value.purchase_items.length > 1) {
        form.value.purchase_items.splice(index, 1);
    } else {
        toast.error("At least one row is required.");
    }
};

const totalGST = computed(() => {
    if (!form.value.accepted) return 0;
    return form.value.purchase_items.reduce((sum, item) => {
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
    return form.value.purchase_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const baseAmount = quantity * price;
        return sum + baseAmount;

    }, 0);
});

const grandTotal = computed(() => {
    const transport = parseFloat(form.value.transport) || 0;
    return totalAmount.value + transport + totalGST.value;
});

const dueAmount = computed(() => {
  const paidNow = parseFloat(form.value.paid) || 0;
  const difference = paidNow - grandTotal.value;

  if (difference > 0) {
    return { type: 'advance', amount: difference };
  } else if (difference < 0) {
    return { type: 'due', amount: Math.abs(difference) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const supplierBalanceExcludingCurrentPurchase = computed(() => {
  return 0;
});

const previousAdvance = computed(() => {
  return 0;
});

const previousDue = computed(() => {
  return 0;
});

const finalBalance = computed(() => {
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

const advanceApplied = computed(() => {
  return 0;
});

const dueReduced = computed(() => {
  return 0;
});

const paymentStatus = computed(() => {
  const paidNow = parseFloat(form.value.paid) || 0;
  if (paidNow === 0) return 'Unpaid';
  else if (paidNow < grandTotal.value) return 'Partial';
  else return 'Paid';
});

const showPaymentModal = ref(false);
const lastActiveElement = ref(null);
const paymentTransportInput = ref(null);

// Move focus to the next logical input/select/button
const moveToNextInput = (event) => {
  const container = document.querySelector('.bg-white.p-8');
  if (!container) return;
  const elements = Array.from(container.querySelectorAll(
    'input:not([disabled]), select:not([disabled]), button:not([disabled]), .vs__search'
  )).filter(el => {
    const rect = el.getBoundingClientRect();
    const isVisible = rect.width > 0 && rect.height > 0;
    const isTrashBtn = el.closest('button')?.classList.contains('bg-red-600');
    const isAddRowBtn = el.closest('button')?.classList.contains('bg-green-600') || el.classList.contains('bg-green-600');
    return isVisible && !isTrashBtn && !isAddRowBtn;
  });
  const currentIndex = elements.indexOf(event.target);
  if (currentIndex !== -1 && currentIndex < elements.length - 1) {
    event.preventDefault();
    elements[currentIndex + 1].focus();
  }
};

// Global escape key handler
const handleGlobalKeydown = (e) => {
  if (e.key === 'Escape') {
    if (showPaymentModal.value) {
      showPaymentModal.value = false;
      e.preventDefault();
    }
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleGlobalKeydown);
  nextTick(() => {
    const supplierSearch = document.querySelector('.vs__search');
    if (supplierSearch) supplierSearch.focus();
  });
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
});

// Watch showPaymentModal to manage focus
watch(showPaymentModal, async (isOpen) => {
  if (isOpen) {
    await nextTick();
    if (paymentTransportInput.value) paymentTransportInput.value.focus();
  } else {
    if (lastActiveElement.value) {
      await nextTick();
      lastActiveElement.value.focus();
      lastActiveElement.value = null;
    }
  }
});

// Show modal first, then submit all
const openPaymentModal = () => {
    const { supplier_id, purchase_items } = form.value;

    if (!supplier_id) {
        toast.error("Please select a customer.");
        return;
    }

    for (let i = 0; i < purchase_items.length; i++) {
        const item = purchase_items[i];
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
    lastActiveElement.value = document.activeElement;
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

    const response = await axios.post(`/purchase/update/${form.value.id}`, payload);
    toast.success(response.data.message);
    showPaymentModal.value = false;

  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

</script>
<template>

    <Head title="Purchase">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('purchase')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Purchase</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Update Purchase</h2>
        <div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Supplier</label>
                    <vSelect
                        v-model="form.supplier_id"
                        :options="suppliers"
                        label="name"
                        :reduce="supplier => supplier.id"
                        placeholder="Search or select supplier"
                        class="w-full"
                        @search="onSupplierSearch"
                        @keydown.enter="moveToNextInput"
                    >
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500">
                                <span v-if="!supplierSearchQuery">Type to search supplier...</span>
                                <span v-else>No suppliers found.</span>
                            </div>
                        </template>
                    </vSelect>
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Invoice / Bill Number</label>
                    <input type="text" name="invoice_no" v-model="form.invoice_no"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter invoice/bill number" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Invoice Date</label>
                    <input type="date" name="purchase_date" v-model="form.purchase_date"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Received Date</label>
                    <input type="date" name="received_date" v-model="form.received_date"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-if="selectedSupplier" class="mt-4 p-4 border rounded bg-gray-100 text-black">
                <p><strong>Name:</strong> {{ selectedSupplier.name }}</p>
                <p><strong>Phone:</strong> {{ selectedSupplier.phone }}</p>
                <p><strong>Address:</strong> {{ selectedSupplier.address }}</p>
                </div>
                <div v-else></div>

                <div class="mt-4 p-4 border rounded bg-gray-50">
                    <label class="block text-black font-medium mb-2">Delivery Mode</label>
                    <select v-model="form.delivery_mode" class="w-full px-4 py-3 mb-4 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] outline-none transition">
                        <option value="By Hand">By Hand</option>
                        <option value="Vehicle">Vehicle</option>
                    </select>

                    <div v-if="form.delivery_mode === 'Vehicle'" class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Transpoter Name</label>
                            <input type="text" v-model="form.vehicle_type" placeholder="e.g. Transpoter Name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688]" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Vehicle Number</label>
                            <input type="text" v-model="form.vehicle_number" placeholder="e.g. MH 01 AB 1234" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688]" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Delivery Person Name</label>
                            <input type="text" v-model="form.delivery_person_name" placeholder="Name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688]" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Delivery Person Phone</label>
                            <input type="text" v-model="form.delivery_person_phone" placeholder="Phone" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688]" />
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-2xl font-bold mb-4 text-[#292688]">Purchase Items</h3>
            </div>

            <!-- Desktop view: Table layout -->
            <table class="hidden md:table w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
                <thead class="bg-[#292688] text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Product</th>
                        <th class="px-4 py-2 text-left">GST</th>
                        <th class="px-4 py-2 text-left">Quantity</th>
                        <th class="px-4 py-2 text-left">Unit Type</th>
                        <th class="px-4 py-2 text-left">Price</th>
                        <th class="px-4 py-2 text-left">Sale Price</th>
                        <th class="px-4 py-2 text-left">Net Amount</th>
                        <th class="px-4 py-2 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in form.purchase_items" :key="index">
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
                                @keydown.enter="moveToNextInput"
                            />
                        </td>

                        <td class="border-t px-4 py-3 min-w-[140px]">
                            <select
                                v-model="item.gst_rate_id"
                                @change="onGstRateChange(item)"
                                @keydown.enter.prevent="moveToNextInput"
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
                                @keydown.enter.prevent="moveToNextInput"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Qty" />
                        </td>
                        <td class="border-t px-4 py-3">
                            {{ item.unit_type }}
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="number" name="price" v-model="item.price" required
                                @keydown.enter.prevent="moveToNextInput"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Price" />
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="number" step="0.01" name="sale_price" v-model="item.sale_price"
                                @keydown.enter.prevent="moveToNextInput"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Sale Price" />
                        </td>
                        <td class="border-t px-4 py-3">
                            ₹  {{ (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) }}
                        </td>

                        <td class="border-t px-4 py-2 flex items-center justify-start">
                            <button @click="removeRow(index)" type="button"
                                class="bg-red-600 text-white px-3 py-1 rounded-md shadow hover:bg-red-700 transition mr-2 mt-2">
                                <i class="bi bi-trash"></i>
                            </button>

                            <button v-if="index === form.purchase_items.length - 1" @click="addRow" type="button"
                                class="bg-green-600 text-white px-3 py-1 rounded-md shadow hover:bg-green-700 transition mt-2">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Mobile view: Card list of items -->
            <div class="md:hidden space-y-4">
                <div v-for="(item, index) in form.purchase_items" :key="'mobile-' + index" class="bg-gray-50 p-4 rounded-xl border border-gray-200 space-y-4">
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
                                @keydown.enter="moveToNextInput"
                            />
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-500 mb-1">GST Rate</label>
                                <select
                                    v-model="item.gst_rate_id"
                                    @change="onGstRateChange(item)"
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm bg-white"
                                >
                                    <option value="" disabled>Select GST</option>
                                    <option v-for="rate in filteredGstRates" :key="rate.id" :value="rate.id">
                                        {{ rate.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity</label>
                                <input type="number" v-model="item.quantity" required
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Qty" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price</label>
                                <input type="number" v-model="item.price" required
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Price" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Sale Price</label>
                                <input type="number" step="0.01" v-model="item.sale_price"
                                    @keydown.enter.prevent="moveToNextInput"
                                    class="w-full px-2 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Sale Price" />
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
         style="z-index: 9999;"
         @click.self="showPaymentModal = false">
        <form @submit.prevent="submitForm" class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-md my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#292688]">Payment Details</h2>
                <button type="button" @click="showPaymentModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa fa-close"></i>
                </button>
            </div>

            <div class="space-y-4 border-t pt-4">

                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Transport Amount</label>
                    <input type="number" ref="paymentTransportInput" v-model="form.transport"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>

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

                <!-- Invoice Balance -->
                <div v-if="finalBalance?.type === 'advance'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Advance</span>
                    <span class="text-green-600 font-bold">₹ {{ finalBalance.amount.toFixed(2) }}</span>
                </div>

                <div v-if="finalBalance?.type === 'due'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Due</span>
                    <span class="text-red-600 font-bold">₹ {{ finalBalance.amount.toFixed(2) }}</span>
                </div>

                <div v-if="finalBalance?.type === 'none'" class="flex justify-between items-center pb-4 border-b border-gray-100">
                    <span class="text-gray-700 font-semibold">Due / Advance</span>
                    <span class="text-gray-600 font-bold">₹ 0.00 (Clear)</span>
                </div>

                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                    <span class="text-gray-700 font-semibold">Payment Status</span>
                    <span class="text-blue-600 font-bold">{{ paymentStatus }}</span>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showPaymentModal = false" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer">Final Submit</button>
                </div>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>
<style>
.v-select .vs__dropdown-toggle {
    min-height: 50px;
    border-radius: 0.75rem !important;
    border-color: #d1d5db;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}
.v-select .vs__selected, .v-select .vs__search {
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1.5;
}
</style>
