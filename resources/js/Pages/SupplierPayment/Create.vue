<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head ,usePage} from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';


const { suppliers } = usePage().props;

const form = ref({
    supplier_id: "",
    amount: "",
    payment_date: "",
    payment_method: "",
    note: "",
});

// Submit the form data
const submitForm = async () => {
  try {
    const response = await axios.post(`/paymentSupplier/store`, form.value);
    toast.success(response.data.message);

    // Reset the form
    form.value = {
      customer_id: "",
      amount: "",
      payment_date: "",
      payment_method: "",
      note: "",
    };
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};
</script>
<template>

    <Head title="Supplier Payment">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('paymentSupplier')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Supplier Payment</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Payment</h2>
        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Supplier</label>
                        <select   name="supplier_id" v-model="form.supplier_id"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Supplier</option>
                            <option v-for="supplier in suppliers" :key="supplier.id"
                                    :value="supplier.id"> {{ supplier.name }}</option>
                        </select>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Amount</label>
                    <input type="number" name="amount" v-model="form.amount"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Date</label>
                    <input type="date" name="payment_date" v-model="form.payment_date"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Payment Date" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Payment Method</label>
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