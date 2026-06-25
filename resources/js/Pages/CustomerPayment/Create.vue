<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head ,usePage} from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';


const { customers } = usePage().props;

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
                form.value.advance_amount_used = Math.min(due, available);
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
            form.value.amount = Math.max(0, due - advanceUsed);
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
            form.value.amount = Math.max(0, due - advanceUsed);
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
    form.value = {
      customer_id: form.value.customer_id,
      sale_id: "",
      amount: "",
      payment_date: today,
      payment_method: "",
      note: "",
      use_advance: false,
      advance_amount_used: 0,
    };
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
                        <select   name="customer_id" v-model="form.customer_id"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Customer</option>
                            <option v-for="customer in customers" :key="customer.id"
                                    :value="customer.id"> {{ customer.name }}</option>
                        </select>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Invoice (Optional)</label>
                    <select name="sale_id" v-model="form.sale_id"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="">Direct Payment (No Invoice)</option>
                        <option v-for="invoice in customerInfo.due_invoices" :key="invoice.id" :value="invoice.id">
                            Inv #{{ invoice.invoice_no }} (Due: ₹{{ invoice.due }})
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
    </AuthenticatedLayout>
</template>