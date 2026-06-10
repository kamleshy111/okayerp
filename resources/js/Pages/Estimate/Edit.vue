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
  estimate: {
    type: Object,
    required: true,
  },
  customers: {
    type: Array,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
  categories: {
    type: Array,
    required: true,
  },
  unitTypes: {
    type: Object,
    required: true,
  }
});

const customers = ref([...props.customers]);
const products = ref([...props.products]);
const categories = props.categories;
const unitTypes = props.unitTypes;
const estimate = props.estimate;

const form = ref({
    customer_id: estimate.customer_id,
    estimate_date: estimate.estimate_date,
    expiry_date: estimate.expiry_date || "",
    grand_total: estimate.grand_total,
    GstAmount: estimate.gst_amount,
    accepted: estimate.accepted == 1,
    total_amount: estimate.total_amount,
    discount: estimate.discount,
    notes: estimate.notes || "",
    estimate_items: estimate.items.map(item => ({
        product_id: item.product_id,
        unit_type: item.unit_type || "",
        sgst: item.sgst || 0,
        cgst: item.cgst || 0,
        quantity: item.quantity || 1,
        price: item.price || 0,
        baseAmount: item.base_price || 0,
    })),
});

const selectedCustomer = ref(customers.value.find(c => c.id == estimate.customer_id) || null);
const showCustomerModal = ref(false);
const newCustomer = ref({
  name: '',
  phone: '',
  email: '',
  gst_number: '',
  address: ''
});

// Product Quick Add Modal State
const showProductModal = ref(false);
const newProduct = ref({
  name: '',
  category_id: '',
  unit_type: '',
  sgst: 0,
  cgst: 0,
  price: 0,
  hsn_code: '',
  description: ''
});
const activeRowIndexForNewProduct = ref(null);

const onCustomerEnterKey = (event) => {
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
    address: ''
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
    customers.value.push(createdCustomer);

    form.value.customer_id = createdCustomer.id;
    selectedCustomer.value = createdCustomer;
    showCustomerModal.value = false;

    newCustomer.value = {
      name: '',
      phone: '',
      email: '',
      gst_number: '',
      address: ''
    };

    toast.success("Customer added successfully!");
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

const openProductModal = (rowIndex) => {
  activeRowIndexForNewProduct.value = rowIndex;
  newProduct.value = {
    name: '',
    category_id: '',
    unit_type: '',
    sgst: 0,
    cgst: 0,
    price: 0,
    hsn_code: '',
    description: ''
  };
  showProductModal.value = true;
};

const submitProduct = async () => {
  try {
    if (!newProduct.value.name) {
      toast.error("Product name is required!");
      return;
    }

    const response = await axios.post('/product/store', newProduct.value);
    const createdProduct = response.data;
    products.value.push(createdProduct);

    // Auto-select the newly created product in the active row
    if (activeRowIndexForNewProduct.value !== null && form.value.estimate_items[activeRowIndexForNewProduct.value]) {
      const targetItem = form.value.estimate_items[activeRowIndexForNewProduct.value];
      targetItem.product_id = createdProduct.id;
      targetItem.unit_type = createdProduct.unit_type;
      targetItem.sgst = createdProduct.sgst;
      targetItem.cgst = createdProduct.cgst;
      targetItem.price = createdProduct.price || 0;
    }

    showProductModal.value = false;
    activeRowIndexForNewProduct.value = null;

    toast.success("Product added successfully!");
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

// Watch Customer ID to find Customer details
watch(() => form.value.customer_id, (newVal) => {
  if (!newVal) {
    selectedCustomer.value = null;
    return;
  }
  selectedCustomer.value = customers.value.find(c => c.id == newVal);
});

// Watch Estimate Items to auto-populate product details (price, SGST, CGST, unit_type)
watch(() => form.value.estimate_items, (newItems) => {
  newItems.forEach(item => {
    const prod = products.value.find(p => p.id === item.product_id);
    if (prod) {
      if (item.unit_type === "") item.unit_type = prod.unit_type;
      if (item.sgst === "" || item.sgst === 0) item.sgst = prod.sgst;
      if (item.cgst === "" || item.cgst === 0) item.cgst = prod.cgst;
      if (item.price === "" || item.price === 0) item.price = prod.price || 0;

      const qty = parseFloat(item.quantity) || 0;
      const prc = parseFloat(item.price) || 0;
      item.baseAmount = qty * prc;
    }
  });
}, { deep: true });

const addRow = () => {
    form.value.estimate_items.push({
        product_id: "",
        unit_type: "",
        sgst: 0,
        cgst: 0,
        quantity: 1,
        price: 0,
        baseAmount: 0,
    });
};

const removeRow = (index) => {
    if (form.value.estimate_items.length > 1) {
        form.value.estimate_items.splice(index, 1);
    } else {
        toast.error("At least one row is required.");
    }
};

const totalGST = computed(() => {
    if (!form.value.accepted) return 0;
    return form.value.estimate_items.reduce((sum, item) => {
        const qty = parseFloat(item.quantity) || 0;
        const prc = parseFloat(item.price) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const baseAmount = qty * prc;
        return sum + (baseAmount * (sgst + cgst) / 100);
    }, 0);
});

const totalAmount = computed(() => {
    return form.value.estimate_items.reduce((sum, item) => {
        const qty = parseFloat(item.quantity) || 0;
        const prc = parseFloat(item.price) || 0;
        return sum + (qty * prc);
    }, 0);
});

const grandTotal = computed(() => {
    let total = totalAmount.value + totalGST.value;
    total -= form.value.discount || 0;
    return Math.max(0, total);
});

const submitForm = async () => {
  const { customer_id, estimate_items } = form.value;

  if (!customer_id) {
      toast.error("Please select a customer.");
      return;
  }

  for (let i = 0; i < estimate_items.length; i++) {
      const item = estimate_items[i];
      if (!item.product_id) {
          toast.error(`Item ${i + 1}: Product is required.`);
          return;
      }
      if (parseFloat(item.quantity) <= 0) {
          toast.error(`Item ${i + 1}: Quantity must be greater than 0.`);
          return;
      }
      if (parseFloat(item.price) < 0) {
          toast.error(`Item ${i + 1}: Price cannot be negative.`);
          return;
      }
  }

  try {
    const payload = {
      ...form.value,
      grand_total: grandTotal.value,
      total_amount: totalAmount.value,
      GstAmount: totalGST.value,
    };

    const response = await axios.post(`/estimate/update/${estimate.id}`, payload);
    toast.success(response.data.message || "Quotation updated successfully!");
    
    // Redirect to list page
    router.visit(route('estimate.index'));
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred while saving the quotation.";
    toast.error(errorMessage);
  }
};
</script>

<template>
    <Head title="Edit Estimate">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class text-sm">
            <a :href="route('estimate.index')" class="text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <i class="bi bi-chevron-left"></i><span>Back to Quotations</span>
            </a>
        </div>
        
        <div class="flex items-center justify-between">
            <h2 class="text-2xl font-bold text-[#292688]">Edit Quotation / Estimate</h2>
            <span class="px-3 py-1 bg-[#2e2c92] text-white rounded-lg text-sm font-semibold">{{ estimate.estimate_no }}</span>
        </div>
        
        <div class="space-y-6">
            <!-- Customer and Dates Selection -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Customer <span class="text-red-500">*</span></label>
                    <vSelect
                        v-model="form.customer_id"
                        :options="customers"
                        label="name"
                        :reduce="customer => customer.id"
                        placeholder="Search or select customer"
                        class="w-full text-black bg-white"
                        @keydown.enter="onCustomerEnterKey"
                    >
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500 text-sm">
                                No customers found.
                                <button
                                    @click.stop="showCustomerModal = true"
                                    class="mt-2 text-blue-600 hover:underline text-xs block"
                                >
                                    ➕ Add New Customer
                                </button>
                            </div>
                        </template>
                    </vSelect>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Estimate Date <span class="text-red-500">*</span></label>
                    <input type="date" v-model="form.estimate_date"
                        class="w-full px-4 py-2 bg-white text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Expiry Date</label>
                    <input type="date" v-model="form.expiry_date"
                        class="w-full px-4 py-2 bg-white text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
            </div>

            <!-- Selected Customer Details -->
            <div v-if="selectedCustomer" class="p-4 border border-gray-200 rounded-lg bg-gray-50 text-sm text-gray-700 grid grid-cols-1 md:grid-cols-3 gap-4">
                <p><strong>Phone:</strong> {{ selectedCustomer.phone }}</p>
                <p><strong>Email:</strong> {{ selectedCustomer.email }}</p>
                <p><strong>Address:</strong> {{ selectedCustomer.address || '-' }}</p>
            </div>

            <!-- Line Items Table -->
            <div class="mt-6">
                <h3 class="text-lg font-bold mb-3 text-[#292688]">Quotation Items</h3>
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-[#292688] text-white text-sm">
                            <tr>
                                <th class="px-4 py-3 text-left w-1/3">Product</th>
                                <th class="px-4 py-3 text-left">GST (%)</th>
                                <th class="px-4 py-3 text-left w-20">Qty</th>
                                <th class="px-4 py-3 text-left">Unit</th>
                                <th class="px-4 py-3 text-left w-32">Price</th>
                                <th class="px-4 py-3 text-left">Net Amount</th>
                                <th class="px-4 py-3 text-center w-40">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            <tr v-for="(item, index) in form.estimate_items" :key="index" class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <vSelect
                                        v-model="item.product_id"
                                        :options="products"
                                        label="name"
                                        :reduce="product => product.id"
                                        placeholder="Search product"
                                        class="w-full text-black bg-white"
                                        append-to-body
                                    >
                                        <template #no-options>
                                            <div class="px-3 py-2 text-gray-500 text-xs">
                                                No products found.
                                                <button
                                                    @click.stop="openProductModal(index)"
                                                    class="mt-1 text-blue-600 hover:underline text-xs block"
                                                >
                                                    ➕ Add New Product
                                                </button>
                                            </div>
                                        </template>
                                    </vSelect>
                                </td>

                                <td class="px-4 py-3 text-gray-600 font-medium">
                                    {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %
                                </td>

                                <td class="px-4 py-3">
                                    <input type="number" v-model="item.quantity" min="1" required
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-center" />
                                </td>

                                <td class="px-4 py-3 text-gray-600">
                                    {{ item.unit_type || '-' }}
                                </td>

                                <td class="px-4 py-3">
                                    <input type="number" step="0.01" v-model="item.price" min="0" required
                                        class="w-full px-2 py-1.5 border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-right" />
                                </td>

                                <td class="px-4 py-3 font-semibold text-gray-800 text-right">
                                    ₹ {{ (parseFloat(item.quantity || 0) * parseFloat(item.price || 0)).toFixed(2) }}
                                </td>

                                <td class="px-4 py-3 text-center space-x-1">
                                    <button @click="removeRow(index)" type="button"
                                        class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded transition font-medium text-xs border border-red-200">
                                        Remove
                                    </button>
                                    <button v-if="index === form.estimate_items.length - 1" @click="addRow" type="button"
                                        class="bg-green-50 hover:bg-green-100 text-green-600 px-3 py-1.5 rounded transition font-medium text-xs border border-green-200">
                                        + Add Item
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tax, Discount, Notes, and Summary Block -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 border-t border-gray-200 pt-6">
                <!-- Notes and GST Config -->
                <div class="space-y-4">
                    <div class="flex items-center">
                        <label class="inline-flex items-center space-x-2.5 cursor-pointer">
                            <input type="checkbox" v-model="form.accepted" class="form-checkbox h-5 w-5 text-[#292688] border-gray-300 rounded focus:ring-[#292688]">
                            <span class="text-sm font-medium text-gray-800">Apply GST Taxes to Estimate</span>
                        </label>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-1.5">Discount (₹)</label>
                        <input type="number" step="0.01" v-model="form.discount" min="0"
                            class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Discount amount" />
                    </div>

                    <div>
                        <label class="block text-gray-700 font-medium mb-1.5">Notes & Remarks</label>
                        <textarea v-model="form.notes" rows="3"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                            placeholder="Add terms, shipping info or private notes..."></textarea>
                    </div>
                </div>

                <!-- Totals Calculation Box -->
                <div class="bg-gray-50 p-6 rounded-xl border border-gray-100 space-y-3 max-w-md ml-auto w-full">
                    <h3 class="text-lg font-bold text-gray-800 border-b border-gray-200 pb-2">Calculation Summary</h3>
                    
                    <div class="flex justify-between items-center text-sm text-gray-600">
                        <span>Total Net (Subtotal):</span>
                        <span class="font-medium">₹ {{ totalAmount.toFixed(2) }}</span>
                    </div>

                    <div v-if="form.accepted" class="flex justify-between items-center text-sm text-gray-600">
                        <span>Total GST Amount:</span>
                        <span class="font-medium text-blue-600">₹ {{ totalGST.toFixed(2) }}</span>
                    </div>

                    <div v-if="form.discount > 0" class="flex justify-between items-center text-sm text-gray-600">
                        <span>Discount Deducted:</span>
                        <span class="font-medium text-red-600">- ₹ {{ parseFloat(form.discount || 0).toFixed(2) }}</span>
                    </div>

                    <div class="flex justify-between items-center text-base font-bold text-gray-900 border-t border-gray-200 pt-3">
                        <span>Estimated Grand Total:</span>
                        <span class="text-lg text-[#292688]">₹ {{ grandTotal.toFixed(2) }}</span>
                    </div>

                    <div class="pt-4 flex gap-3">
                        <button @click="submitForm" class="flex-1 bg-[#2E2C92] hover:bg-[#201e6a] text-white py-2.5 rounded-lg font-semibold shadow-md transition duration-200 text-center">
                            Save Changes
                        </button>
                        <a :href="route('estimate.index')" class="px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold text-gray-700 transition duration-200 text-center text-sm">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Customer Modal -->
    <div v-if="showCustomerModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="z-index: 99999;">
        <div class="bg-white p-6 rounded-xl w-full max-w-md shadow-lg">
            <h2 class="text-xl font-bold mb-4 text-[#2E2C92]">Add New Customer</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" v-model="newCustomer.name" required class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                    <input type="text" v-model="newCustomer.phone" required class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" v-model="newCustomer.email" required class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">GST Number</label>
                    <input type="text" v-model="newCustomer.gst_number" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" placeholder="e.g. 22AAAAA1111A1Z1" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea v-model="newCustomer.address" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" rows="2"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <button
                    @click="submitCustomer"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium transition-colors"
                >
                    Save Customer
                </button>
                <button
                    @click="showCustomerModal = false"
                    class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded font-medium transition-colors"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Add Product Modal -->
    <div v-if="showProductModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50" style="z-index: 99999;">
        <div class="bg-white p-6 rounded-xl w-full max-w-lg shadow-lg max-h-[90vh] overflow-y-auto">
            <h2 class="text-xl font-bold mb-4 text-[#2E2C92]">Add New Product</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Product Name <span class="text-red-500">*</span></label>
                    <input type="text" v-model="newProduct.name" required class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select v-model="newProduct.category_id" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none">
                            <option value="">Select Category</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Type</label>
                        <select v-model="newProduct.unit_type" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none">
                            <option value="">Select Unit</option>
                            <option v-for="(label, val) in unitTypes" :key="val" :value="val">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SGST (%)</label>
                        <input type="number" step="0.01" v-model.number="newProduct.sgst" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CGST (%)</label>
                        <input type="number" step="0.01" v-model.number="newProduct.cgst" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">HSN/SAC Code</label>
                        <input type="text" v-model="newProduct.hsn_code" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Selling Price (₹)</label>
                        <input type="number" step="0.01" v-model.number="newProduct.price" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea v-model="newProduct.description" class="w-full border px-3 py-2 rounded focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" rows="2"></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <button
                    @click="submitProduct"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-medium transition-colors"
                >
                    Save Product
                </button>
                <button
                    @click="showProductModal = false"
                    class="bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded font-medium transition-colors"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>

    </AuthenticatedLayout>
</template>
