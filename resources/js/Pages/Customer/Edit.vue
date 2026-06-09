<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import axios from 'axios';


// Access props
const { customerDetail } = usePage().props;

const form = ref({
    name: customerDetail.name,
    email: customerDetail.email,
    phone: customerDetail.phone,
    address: customerDetail.address,
    gst_number: customerDetail.gst_number || '',
});

// Form submit handler
const submitForm = async () => {
    try {
        const response = await axios.post(`/customer/update/${customerDetail.id}`, form.value);
        toast.success(response.data.message);
    } catch (error) {
        const errorMessage = error.response?.data?.message || 'An error occurred. Please try again.';
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
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Update Customer</h2>
        <form @submit.prevent="submitForm">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Name</label>
                    <input type="text" v-model="form.name" name="name" 
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email</label>
                    <input type="email" v-model="form.email" name="email"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Phone</label>
                    <input type="number" v-model="form.phone" name="phone"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Phone" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">GST Number</label>
                    <input type="text" v-model="form.gst_number" name="gst_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="GST Number" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6">
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" v-model="form.address" name="address"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="address" />
                </div>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Update</button>
            </div>

  </form>
</div>

    </AuthenticatedLayout>

</template>