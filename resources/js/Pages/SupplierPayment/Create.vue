<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const suppliers = ref([]);
const supplierSearchQuery = ref("");

const today = new Date().toISOString().split('T')[0];

const form = ref({
    supplier_id: "",
    amount: "",
    payment_date: today,
    payment_method: "",
    note: "",
});

const onSupplierSearch = async (search, loading) => {
  supplierSearchQuery.value = search;
  if (!search.trim()) {
    if (form.value.supplier_id) {
      const selected = suppliers.value.find(s => s.id === form.value.supplier_id);
      suppliers.value = selected ? [selected] : [];
    } else {
      suppliers.value = [];
    }
    return;
  }
  try {
    const response = await axios.get(`/supplier/search?query=${encodeURIComponent(search)}`);
    suppliers.value = response.data;
  } catch (error) {
    console.error("Error fetching suppliers:", error);
  }
};

// Submit the form data
const submitForm = async () => {
  try {
    const response = await axios.post(`/paymentSupplier/store`, form.value);
    toast.success(response.data.message);

    // Reset the form
    form.value = {
      supplier_id: "",
      amount: "",
      payment_date: today,
      payment_method: "",
      note: "",
    };
    suppliers.value = [];
    supplierSearchQuery.value = "";
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
                    <label class="block text-black font-medium mb-2">Supplier <span class="text-red-500">*</span></label>
                    <vSelect
                        v-model="form.supplier_id"
                        :options="suppliers"
                        label="name"
                        :reduce="supplier => supplier.id"
                        placeholder="Search or select supplier"
                        class="w-full text-black bg-white"
                        @search="onSupplierSearch"
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
                    <label class="block text-black font-medium mb-2">Amount <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" v-model="form.amount"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" />
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

