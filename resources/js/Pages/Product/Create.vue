<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head ,usePage} from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

const { categories } = usePage().props;
const { unitTypes } = usePage().props;

const form = ref({
    name: "",
    category_id: "",
    unit_type: "",
    sgst: "",
    cgst: "",
    hsn_code: "",
    price: "",
    description: "",
});



// Submit the form data
const submitForm = async () => {
  try {
    const response = await axios.post(`/product/store`, form.value);
    toast.success(response.data.message);

    // Reset the form
    form.value = {
      name: "",
      category_id: "",
      unit_type: "",
      sgst: "",
      cgst: "",
      hsn_code: "",
      price: "",
      description: "",
 
    };
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};
</script>
<template>

    <Head title="Product">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('product')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Product</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Product</h2>
        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Name</label>
                    <input type="text" name="Name" v-model="form.name"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter name" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Category</label>
                        <select   name="category_id" v-model="form.category_id"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Category</option>
                            <option v-for="category in categories" :key="category.id"
                                    :value="category.id"> {{ category.name }}</option>
                        </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">SGST (%)</label>
                    <input type="number" name="sgst" v-model="form.sgst"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="SGST (%)" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">CGST (%)</label>
                    <input type="number" name="cgst" v-model="form.cgst"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="CGST (%)" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">HSN/SAC Code</label>
                    <input type="text" name="hsn_code" v-model="form.hsn_code"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter HSN/SAC code" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Unit Type</label>
                    <select name="unit_type" v-model="form.unit_type"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Unit</option>
                        <option v-for="(label, value) in unitTypes" :key="value" :value="value">
                            {{ label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-7">
                <label class="block text-black font-medium mb-2">Description</label>
                <textarea name="description" v-model="form.description" rows="3"
                    class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                    placeholder="Description"></textarea>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Save</button>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>