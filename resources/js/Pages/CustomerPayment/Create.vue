<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const customers = ref([]);
const customerSearchQuery = ref("");

const customerInfo = ref({
    advance_amount: 0,
    due_invoices: []
});

const today = new Date().toISOString().split('T')[0];

const form = ref({
    customer_id: "",
    sale_id: "",
    amount: "",
    payment_date: today,
    payment_method: "",
    note: "",
    use_advance: false,
    advance_amount_used: 0,
});

const onCustomerSearch = async (search, loading) => {
  customerSearchQuery.value = search;
  if (!search.trim()) {
    if (form.value.customer_id) {
      const selected = customers.value.find(c => c.id === form.value.customer_id);
      customers.value = selected ? [selected] : [];
    } else {
      customers.value = [];
    }
    return;
  }
  try {
    const response = await axios.get(`/customer/search?query=${encodeURIComponent(search)}`);
    customers.value = response.data;
  } catch (error) {
    console.error("Error fetching customers:", error);
  }
};

import { watch } from 'vue';

watch(() => form.value.customer_id, async (newId) => {
    if (newId) {
        try {
            const res = await axios.get(`/customer/${newId}/payment-info`);
            customerInfo.value = res.data;
            form.value.sale_id = "";
            form.value.use_advance = false;
            form.value.advance_amount_used = 0;
            // Optionally auto-set the max advance
        } catch(e) {
            console.error("Error fetching payment info");
        }
    }
});

watch(() => form.value.use_advance, (newVal) => {
    if (!newVal) {
        form.value.advance_amount_used = 0;
    } else {
        if (form.value.sale_id) {
            const invoice = customerInfo.value.due_invoices.find(inv => inv.id === form.value.sale_id);
            if (invoice) {
                let due = parseFloat(invoice.due) || 0;
                let available = parseFloat(customerInfo.value.advance_amount) || 0;
                form.value.advance_amount_used = parseFloat(Math.min(due, available).toFixed(2));
            }
        }
    }
});

watch(() => form.value.sale_id, (newSaleId) => {
    if (newSaleId) {
        const invoice = customerInfo.value.due_invoices.find(inv => inv.id === newSaleId);
        if (invoice) {
            let due = parseFloat(invoice.due) || 0;
            let advanceUsed = form.value.use_advance ? parseFloat(form.value.advance_amount_used) || 0 : 0;
            form.value.amount = parseFloat(Math.max(0, due - advanceUsed).toFixed(2));
        }
    } else {
        form.value.amount = "";
    }
});

watch(() => form.value.advance_amount_used, (newVal) => {
    if (form.value.sale_id) {
        const invoice = customerInfo.value.due_invoices.find(inv => inv.id === form.value.sale_id);
        if (invoice) {
            let due = parseFloat(invoice.due) || 0;
            let advanceUsed = parseFloat(newVal) || 0;
            form.value.amount = parseFloat(Math.max(0, due - advanceUsed).toFixed(2));
        }
    }
});

// Submit the form data
const submitForm = async () => {
  try {
    const response = await axios.post(`/paymentsCustomer/store`, form.value);
    toast.success(response.data.message);

    if (response.data.receipt_url) {
      window.location.href = response.data.receipt_url;
      return;
    }

    // Refresh payment info for selected customer
    if(form.value.customer_id) {
        const res = await axios.get(`/customer/${form.value.customer_id}/payment-info`);
        customerInfo.value = res.data;
    }

    // Reset the form
    const currentCustomerId = form.value.customer_id;
    const currentCustomer = customers.value.find(c => c.id === currentCustomerId);
    form.value = {
      customer_id: currentCustomerId,
      sale_id: "",
      amount: "",
      payment_date: today,
      payment_method: "",
      note: "",
      use_advance: false,
      advance_amount_used: 0,
    };
    customers.value = currentCustomer ? [currentCustomer] : [];
    customerSearchQuery.value = "";
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    if (typeof errorMessage === 'object') {
        Object.values(errorMessage).forEach(msgArray => {
            if(Array.isArray(msgArray)) msgArray.forEach(m => toast.error(m));
            else toast.error(msgArray);
        });
    } else {
        toast.error(errorMessage);
    }
  }
};

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

    const response = await axios.post('/customer/store', newCustomer.value);
    const createdCustomer = response.data;
    customers.value.push(createdCustomer);

    form.value.customer_id = createdCustomer.id;
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
</script>
<template>

    <Head title="Customer Payment">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('paymentsCustomer')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Customer Payment</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Payment</h2>
        <form @submit.prevent="submitForm">
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
                        @search="onCustomerSearch"
                    >
                        <template #no-options="{ search, searching, loading }">
                            <div class="px-3 py-2 text-gray-500">
                                <span v-if="!search">Type to search customer...</span>
                                <span v-else>No customers found.</span>
                                <button
                                    type="button"
                                    @click.stop="openCustomerModalWithName(search)"
                                    class="mt-2 block text-blue-600 hover:underline text-sm"
                                >
                                    ➕ Add New Customer
                                </button>
                            </div>
                        </template>
                    </vSelect>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Invoice (Optional)</label>
                    <select name="sale_id" v-model="form.sale_id"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="">Direct Payment (No Invoice)</option>
                        <option v-for="invoice in customerInfo.due_invoices" :key="invoice.id" :value="invoice.id">
                            Inv #{{ invoice.invoice_no }} (Due: ₹{{ parseFloat(invoice.due).toFixed(2) }})
                        </option>
                    </select>
                </div>
            </div>



            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Cash/Bank Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" v-model="form.amount"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter remaining cash/bank amount" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" v-model="form.payment_date"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Payment Date" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Payment Method <span class="text-red-500">*</span></label>
                    <select name="payment_method" v-model="form.payment_method"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Payment Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>

                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">

                <div>
                    <label class="block text-black font-medium mb-2">Note</label>
                    <input type="note" name="note" v-model="form.note"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Note"/>
                </div>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Save</button>
            </div>
        </form>
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
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    @click="submitCustomer"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer"
                >
                    Save Customer
                </button>
            </div>
        </div>
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
