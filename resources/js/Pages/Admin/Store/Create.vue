<script setup>
import { ref, nextTick, onMounted  } from 'vue';
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
    bank_name: "",
    account_number: "",
    ifsc_code: "",
    branch_name: "",
    gstin: "",
});

// name input ref
const nameInput = ref(null);

// Page open focus
onMounted(() => {
  nameInput.value?.focus();
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
      bank_name: "",
      account_number: "",
      ifsc_code: "",
      branch_name: "",
      gstin: "",
    };
    
    await nextTick();
    nameInput.value?.focus();

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
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('store')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Store</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Store</h2>
        <form @submit.prevent="submitForm">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Name</label>
                    <input  ref="nameInput" type="text" name="name" v-model="form.name" required
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
                    <input type="number" name="phone" v-model="form.phone" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter phone number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" name="address" v-model="form.address" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter address" />
                </div>
            </div>

            <!-- Bank Details -->
            <div class="border-t border-gray-200 mt-7 pt-7">
                <h3 class="text-lg font-bold mb-4 text-[#292688]">Bank Details</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-black font-medium mb-2">Bank Name</label>
                        <input type="text" name="bank_name" v-model="form.bank_name"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter bank name" />
                    </div>
                    <div>
                        <label class="block text-black font-medium mb-2">Account Number</label>
                        <input type="text" name="account_number" v-model="form.account_number"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter account number" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                    <div>
                        <label class="block text-black font-medium mb-2">IFSC Code</label>
                        <input type="text" name="ifsc_code" v-model="form.ifsc_code"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter IFSC code" />
                    </div>
                    <div>
                        <label class="block text-black font-medium mb-2">Branch Name</label>
                        <input type="text" name="branch_name" v-model="form.branch_name"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter branch name" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                    <div>
                        <label class="block text-black font-medium mb-2">GSTIN</label>
                        <input type="text" name="gstin" v-model="form.gstin"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter GSTIN" />
                    </div>
                </div>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Save</button>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>