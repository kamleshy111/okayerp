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
    password: "",
    password_confirmation: "",
});

// Submit the form data
const submitForm = async () => {
  // client-side check: password & confirm password match
  if (form.value.password !== form.value.password_confirmation) {
    toast.error("Passwords do not match!");
    return;
  }

  try {
    const response = await axios.post(`/store/create`, form.value);
    toast.success(response.data.message || "Store created successfully!");

    // Reset the form
    form.value = {
      name: "",
      email: "",
      phone: "",
      address: "",
      password: "",
      password_confirmation: "",
    };
  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      (error.response?.data?.errors
        ? Object.values(error.response.data.errors).flat().join(", ")
        : "An error occurred. Please try again.");
    toast.error(errorMessage);
  }
};
</script>
<template>

    <Head title="Customer">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add User</h2>
        <form @submit.prevent="submitForm">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Name</label>
                    <input type="text" name="name" v-model="form.name" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter full name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email</label>
                    <input type="text" name="email" v-model="form.email" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Password</label>
                    <input type="password" name="password" v-model="form.password" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter password" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Confirm Password</label>
                    <input type="password" name="password_confirmation" v-model="form.password_confirmation" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter confirm password" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Phone No</label>
                    <input type="number" name="phone" v-model="form.phone"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter phone number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Role</label>
                    <select name="role" v-model="form.role" required
                        class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select role</option>
                        <option value="admin">Admin</option>
                        <option value="store">Store</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
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