<script setup>
import { ref, watch, computed  } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";


const props = defineProps({
  suppliers: {
    type: Array,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
});

const suppliers = props.suppliers;
const products = props.products;

const form = ref({
    supplier_id: "",
    invoice_no: "",
    purchase_date: new Date().toISOString().substring(0, 10),
    transport: 0,
    grand_total: "",
    GstAmount: "",
    accepted: true,
    paid: 0,
    payment_method: '',
    purchase_items: [{
            product_id: "",
            unit_type: "",
            sgst: "",
            cgst: "",
            quantity: "",
            price: "",
            baseAmount: "",
    }],
});

const selectedSupplier = ref(null);
const showPaymentModal = ref(false);
const supplierData = ref(null);

const showSupplierModal = ref(false);

const onEnterKey = (event) => {
  // Only trigger if no supplier matches the search
  if (!form.supplier_id && selectedSupplier.value === null) {
    openSupplierModalWithName(event.target.value);
  }
};


const newSupplier = ref({
  name: '',
  phone: '',
  email: '',
  address: '',
  city: '',
  district: '',
  state: '',
  country: '',
  pin_code: '',
  gstin: ''
});

const openSupplierModalWithName = (name) => {
  newSupplier.value = {
    name: name || '',
    phone: '',
    email: '',
    address: '',
    city: '',
    district: '',
    state: '',
    country: '',
    pin_code: '',
    gstin: ''
  };
  showSupplierModal.value = true;
};

const submitSupplier = async () => {
  try {
    // Make sure name is not empty
    if (!newSupplier.value.name) {
      toast.error("Supplier name is required!");
      return;
    }

    if (!newSupplier.value.phone) {
      toast.error("Supplier phone is required!");
      return;
    }

    if (!newSupplier.value.email) {
      toast.error("Supplier email is required!");
      return;
    }

    // Send POST request to your backend
    const response = await axios.post('/supplier/store', newSupplier.value);

    // Assume the response returns the newly created supplier object
    const createdSupplier = response.data;

    // Add the new supplier to the options
    suppliers.push(createdSupplier);

    // Select the new supplier automatically
    form.supplier_id = createdSupplier.id;
    selectedSupplier.value = createdSupplier;

    // Close the modal
    showSupplierModal.value = false;

    // Clear newSupplier
    newSupplier.value = {
      name: '',
      phone: '',
      email: '',
      address: '',
      city: '',
      district: '',
      state: '',
      country: '',
      pin_code: '',
      gstin: ''
    };

    toast.success("Supplier added successfully!");
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};


// Watch for supplier change
watch(() => form.value.supplier_id, (newVal) => {
  if (!newVal || !Array.isArray(suppliers)) {
    selectedSupplier.value = null;
    return;
  }
  selectedSupplier.value = suppliers.find(s => s.id == newVal);
});

// Watch for product change in each row to update unit_type
watch(() => form.value.purchase_items, (newSaleItems) => {
  newSaleItems.forEach(item => {
    const selectedProduct = products.find(product => product.id === item.product_id);
    if (selectedProduct) {
      item.unit_type = selectedProduct.unit_type;
      item.sgst = selectedProduct.sgst;
      item.cgst = selectedProduct.cgst;

      const quantity = parseFloat(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      const baseAmount = quantity * price;
      item.baseAmount = baseAmount;
    }
  });
}, { deep: true });

// Watcher to fetch supplier data when selected supplier changes
watch(
  () => selectedSupplier.value, // watch the entire object
  async (supplier) => {
    const newId = supplier?.id;

    if (newId) {
      try {
        const response = await axios.get(`/purchase/payment/${newId}`);
        supplierData.value = response.data;
        console.log('Fetched supplier data:', supplierData.value);
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

const addRow = () => {
    // Add a new row to the purchase_items array
    form.value.purchase_items.push({
        product_id: "",
        unit_type: "",
        quantity: "",
        price: "",
        gst: ""
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

const paymentStatus = computed(() => {
  if (!supplierData.value) return 'Unpaid';

  const previousAdvance = parseFloat(supplierData.value.advance_amount) || 0;
  const previousDue = parseFloat(supplierData.value.due_amount) || 0;
  const paidNow = parseFloat(form.value.paid) || 0;

  const previousNet = previousAdvance - previousDue;
  const currentNet = paidNow - grandTotal.value;
  const finalNet = previousNet + currentNet;

  if (paidNow === 0 && previousDue > 0) return 'Unpaid';
  else if (finalNet === 0) return 'Paid';
  else if (finalNet < 0) return 'Partial';
  else return 'Advance';
});

// Show modal first, then submit all
const openPaymentModal = () => {
    const { supplier_id, purchase_items } = form.value;

    if (!supplier_id) {
        toast.error("Please select a supplier.");
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

    showPaymentModal.value = true;
};

//add 23/08/25

const finalBalance = computed(() => {
  if (!supplierData.value) return null;

  const previousAdvance = parseFloat(supplierData.value.advance_amount) || 0;
  const previousDue = parseFloat(supplierData.value.due_amount) || 0;
  const paidNow = parseFloat(form.value.paid) || 0;

  // supplier ka pehle ka net balance (advance - due)
  const previousNet = previousAdvance - previousDue;

  // is baar ka balance (paid - grandTotal)
  const currentNet = paidNow - grandTotal.value;

  // dono mila ke final
  const finalNet = previousNet + currentNet;

  if (finalNet > 0) {
    return { type: 'advance', amount: finalNet };
  } else if (finalNet < 0) {
    return { type: 'due', amount: Math.abs(finalNet) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const advanceApplied = computed(() => {
  if (!supplierData.value) return 0;
  const previousAdvance = parseFloat(supplierData.value.advance_amount) || 0;
  const paidNow = parseFloat(form.value.paid) || 0;
  return Math.min(previousAdvance, Math.max(0, grandTotal.value - paidNow));
});

const dueReduced = computed(() => {
  if (!supplierData.value) return 0;
  const previousDue = parseFloat(supplierData.value.due_amount) || 0;
  const paidNow = parseFloat(form.value.paid) || 0;
  return Math.min(previousDue, Math.max(0, paidNow - grandTotal.value));
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

    const response = await axios.post(`/purchase/store`, payload);
    toast.success(response.data.message);
    showPaymentModal.value = false;

    // Reset form
    form.value = {
      supplier_id: "",
      invoice_no: "",
      purchase_date: new Date().toISOString().substring(0, 10),
      transport: 0,
      grand_total: "",
      GstAmount: "",
      accepted: true,
      total_amount: "",
      paid: 0,
      payment_method: '',
      payment_status: "",
      purchase_items: [{
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
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Purchase</h2>
        <div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Supplier</label>
                        <vSelect
                                v-model="form.supplier_id"
                                :options="suppliers"
                                label="name"
                                :reduce="supplier => supplier.id"
                                placeholder="Search or select supplier"
                                class="w-full"
                                @keydown.enter="onEnterKey"
                            >
                                <!-- Shown when there are no options at all -->
                                <template #no-options>
                                    <div class="px-3 py-2 text-gray-500">
                                        No suppliers found.
                                        <button
                                            @click.stop="showSupplierModal = true"
                                            class="mt-2 text-blue-600 hover:underline text-sm"
                                        >
                                            ➕ Add New Supplier
                                        </button>
                                        
                                    </div>
                                </template>

                                <!-- Shown when search returns no result -->
                                <!-- <template #no-results>
                                    <div class="px-3 py-2 text-gray-500">
                                        No results found.
                                        <button
                                         @click.stop="openSupplierModalWithName(searchText)"
                                           
                                            class="mt-2 text-blue-600 hover:underline text-sm"
                                        >
                                            ➕ Add "<strong>{{ searchText }}</strong>" as Supplier
                                        </button>
                                    </div>
                                </template> -->
                        </vSelect>

                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Invoice / Bill Number</label>
                    <input type="text" name="invoice_no" v-model="form.invoice_no"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter invoice/bill number" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Purchase Date</label>
                    <input type="date" name="purchase_date" v-model="form.purchase_date"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-if="selectedSupplier" class="mt-4 p-4 border rounded bg-gray-100 text-black">
                <p><strong>Phone:</strong> {{ selectedSupplier.phone }}</p>
                <p><strong>Email:</strong> {{ selectedSupplier.email }}</p>
                <p><strong>Address:</strong> {{ selectedSupplier.address }}</p>
                </div>
            </div>

            <div class="mt-6">
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
                            />
                        </td>

                        <td class="border-t px-4 py-3">
                            {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="text" name="quantity" v-model="item.quantity" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Qty" />
                        </td>
                        <td class="border-t px-4 py-3">
                            {{ item.unit_type }}
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="text" name="price" v-model="item.price" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Price" />
                        </td>
                        <td class="border-t px-4 py-3">
                            ₹  {{ (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) }}
                        </td>

                        <td class="border-t px-4 py-2">
                            <button @click="removeRow(index)" type="button"
                                class="bg-red-600 text-white px-4 py-1 rounded-md shadow hover:bg-red-700 transition mr-2 mt-2">
                                Remove
                            </button>

                            <button v-if="index === form.purchase_items.length - 1" @click="addRow" type="button"
                                class="bg-green-600 text-white px-4 py-1 rounded-md shadow hover:bg-green-700 transition mt-2">
                                Add Items
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
                            />
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Quantity</label>
                                <input type="text" v-model="item.quantity" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Qty" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Price</label>
                                <input type="text" v-model="item.price" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                                    placeholder="Price" />
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 bg-white p-3 rounded-lg border border-gray-100 text-xs font-medium text-gray-500">
                            <div>
                                <span class="block text-gray-400">GST</span>
                                <span class="text-gray-800 font-semibold">{{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %</span>
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

    <!-- Add Supplier Modal -->
    <div v-if="showSupplierModal" 
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6" 
         style="z-index: 99999;"
         @click.self="showSupplierModal = false">
        <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-md my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold mb-4 text-[#292688]">Add New Supplier</h2>
                <button @click="showSupplierModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="fa fa-close"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" v-model="newSupplier.name" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" v-model="newSupplier.phone" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" v-model="newSupplier.email" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea v-model="newSupplier.address" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" rows="2"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" v-model="newSupplier.city" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <input type="text" v-model="newSupplier.district" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" v-model="newSupplier.state" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <input type="text" v-model="newSupplier.country" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PIN Code</label>
                        <input type="text" v-model="newSupplier.pin_code" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
                        <input type="text" v-model="newSupplier.gstin" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" placeholder="e.g. 09KEHPS1695A1Z3" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button
                    @click="showSupplierModal = false"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer font-medium"
                >
                    Cancel
                </button>
                <button
                    @click="submitSupplier"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer font-medium"
                >
                    Save Supplier
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
            
            <div class="space-y-4 border-t pt-4">

                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Transport Amount</label>
                    <input type="text" v-model="form.transport"
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
                        <option value="Null">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>

                <!-- Paid Amount -->
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Paid Amount</label>
                    <input type="text" v-model="form.paid"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" min="0" />
                </div>

                <!-- Previous Balances -->
                <div v-if="supplierData?.advance_amount && supplierData.advance_amount !== '0.00' && supplierData.advance_amount !== 0 && supplierData.advance_amount !== '0'" 
                    class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Previous Advance</span>
                    <span class="text-green-600 font-bold">₹ {{ supplierData.advance_amount }}</span>
                </div>

                <div v-if="supplierData?.due_amount && supplierData.due_amount !== '0.00' && supplierData.due_amount !== 0 && supplierData.due_amount !== '0'"
                    class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Previous Due</span>
                    <span class="text-red-600 font-bold">₹ {{ supplierData.due_amount }}</span>
                </div>

                <!-- Wallet / Due Adjustments -->
                <div v-if="advanceApplied > 0" class="flex justify-between items-center text-sm text-gray-500">
                    <span>Advance Applied</span>
                    <span class="font-medium text-blue-600">- ₹ {{ advanceApplied.toFixed(2) }}</span>
                </div>

                <div v-if="dueReduced > 0" class="flex justify-between items-center text-sm text-gray-500">
                    <span>Applied to Previous Due</span>
                    <span class="font-medium text-green-600">- ₹ {{ dueReduced.toFixed(2) }}</span>
                </div>

                <!-- Final Balance after this payment -->
                <div v-if="finalBalance?.type === 'advance'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Advance</span>
                    <span class="text-green-600 font-bold">₹ {{ finalBalance.amount }}</span>
                </div>

                <div v-if="finalBalance?.type === 'due'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Due</span>
                    <span class="text-red-600 font-bold">₹ {{ finalBalance.amount }}</span>
                </div>

                <div v-if="finalBalance?.type === 'none'" class="flex justify-between items-center pb-4 border-b border-gray-100">
                    <span class="text-gray-700 font-semibold">Final Balance</span>
                    <span class="text-gray-600 font-bold">₹ 0.00 (Clear)</span>
                </div>

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
    </AuthenticatedLayout>
</template>