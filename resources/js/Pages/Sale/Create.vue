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

const form = ref({
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
  address: ''
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
    customers.push(createdCustomer);

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

// Watch Customer
watch(() => form.value.customer_id, (newVal) => {
  if (!newVal || !Array.isArray(customers)) {
    selectedCustomer.value = null;
    return;
  }
  selectedCustomer.value = customers.find(s => s.id == newVal);
});

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

const addRow = () => {
    // Add a new row to the sale_items array
    form.value.sale_items.push({
        product_id: "",
        unit_type: "",
        quantity: "",
        price: "",
        gst: ""
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

  const previousAdvance = parseFloat(customerData.value.advance_amount) || 0;
  const previousDue = parseFloat(customerData.value.due_amount) || 0;
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

  const previousAdvance = parseFloat(customerData.value.advance_amount) || 0;
  const previousDue = parseFloat(customerData.value.due_amount) || 0;
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
                        @keydown.enter="onEnterKey"
                    >
                        <!-- Shown when there are no options at all -->
                        <template #no-options>
                            <div class="px-3 py-2 text-gray-500">
                                No customers found.
                                <button
                                    @click.stop="showCustomerModal = true"
                                    class="mt-2 text-blue-600 hover:underline text-sm"
                                >
                                    ➕ Add New Customer
                                </button>
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

            <table class="w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
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
                            />
                        </td>

                        <td class="border-t px-4 py-3">
                            {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %
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
            <div class="flex justify-end mt-5">
            <button @click="openPaymentModal" class="bg-[#2E2C92] text-white px-4 py-2 rounded">
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

            
            <div class="mt-6 space-y-4 border-t pt-4">
                <div class="flex justify-between items-center">
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" v-model="form.accepted" class="form-checkbox h-5 w-5 text-[#292688]">
                        <span class="text-sm text-gray-700">Apply To GST</span>
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

                <!-- Previous Balances -->
                <div v-if="customerData?.advance_amount && customerData.advance_amount !== '0.00' && finalBalance?.type === 'advance'" 
                    class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Before Advance</span>
                    <span class="text-green-600 font-bold">₹ {{ customerData.advance_amount }}</span>
                </div>

                <div v-if="customerData?.due_amount && customerData.due_amount !== '0.00' && finalBalance?.type === 'due' && (form.paid || 0) <= Number(customerData.due_amount)"
                    class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Before Due</span>
                    <span class="text-red-600 font-bold">₹ {{ customerData.due_amount }}</span>
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

                <!-- Payment Status -->
                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Payment Status</span>
                    <span class="text-blue-600 font-bold">{{ paymentStatus }}</span>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-between">
                    <button @click="submitForm" class="bg-green-600 text-white px-4 py-2 rounded">Final Submit</button>
                    <button @click="showPaymentModal = false" class="bg-gray-300 text-black px-4 py-2 rounded">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
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
    </AuthenticatedLayout>
</template>