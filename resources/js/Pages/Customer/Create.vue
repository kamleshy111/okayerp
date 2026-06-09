<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

const form = ref({
    name: "",
    email: "",
    phone: "",
    address: "",
    gst_number: "",
});

// Submit the form data
const submitForm = async () => {
  try {
    const response = await axios.post(`/customer/store`, form.value);
    toast.success(response.data.message);

    // Reset the form
    form.value = {
      name: "",
      email: "",
      phone: "",
      address: "",
      gst_number: "",
 
    };
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};
</script>
<template>

    <Head title="Customer">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('customer')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Customer</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Customer</h2>
        <form @submit.prevent="submitForm">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Full Name</label>
                    <input type="text" name="name" v-model="form.name" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter full name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email</label>
                    <input type="text" name="email" v-model="form.email"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Phone No</label>
                    <input type="text" name="phone" v-model="form.phone"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter phone number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">GST Number</label>
                    <input type="text" name="gst_number" v-model="form.gst_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter GSTIN (optional)" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" name="address" v-model="form.address"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter address" />
                </div>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Save</button>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>