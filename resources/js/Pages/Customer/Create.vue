<script setup>
import { ref, computed, watch } from 'vue';
import vSelect from 'vue3-select';
import 'vue3-select/dist/vue3-select.css';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

const page = usePage();

const form = ref({
    name: "",
    email: "",
    phone: "",
    address: "",
    gst_number: "",
    pan_number: "",
    cin_number: "",
    city: "",
    district: "",
    state: "",
    country: "",
    pin_code: "",
});

const availableDistricts = computed(() => {
    if (!form.value.state) return [];
    const stateName = form.value.state;
    const statesData = page.props.state_cities || {};
    const lookupKey = Object.keys(statesData).find(
        key => key.toLowerCase().replace(/[^a-z0-9]/g, '') === stateName.toLowerCase().replace(/[^a-z0-9]/g, '')
    );
    return lookupKey ? statesData[lookupKey] : [];
});

watch(() => form.value.state, (newVal, oldVal) => {
    if (oldVal !== undefined) {
        form.value.district = "";
        form.value.city = "";
    }
    if (newVal) {
        form.value.country = "India";
    }
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
      pan_number: "",
      cin_number: "",
      city: "",
      district: "",
      state: "",
      country: "",
      pin_code: "",
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
                    <label class="block text-black font-medium mb-2">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" v-model="form.name" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter full name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email <span class="text-red-500">*</span></label>
                    <input type="text" name="email" v-model="form.email" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Phone No <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" v-model="form.phone" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter phone number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">GST Number <span v-if="!form.pan_number" class="text-red-500">*</span></label>
                    <input type="text" name="gst_number" v-model="form.gst_number" :required="!form.pan_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter GSTIN" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" name="address" v-model="form.address"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter address" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">State</label>
                    <v-select
                        :options="$page.props.gst_states"
                        label="display"
                        :reduce="state => state.name"
                        v-model="form.state"
                        placeholder="Search & Select State"
                        class="w-full"
                    ></v-select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">District</label>
                    <v-select
                        :options="availableDistricts"
                        v-model="form.district"
                        placeholder="Search & Select District"
                        class="w-full"
                        :disabled="!form.state"
                    ></v-select>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">City</label>
                    <input type="text" name="city" v-model="form.city"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter City" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Country</label>
                    <select name="country" v-model="form.country"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Country</option>
                        <option v-for="c in $page.props.countries" :key="c" :value="c">
                            {{ c }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">PIN Code</label>
                    <input type="text" name="pin_code" v-model="form.pin_code"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter PIN Code" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">PAN Number <span v-if="!form.gst_number" class="text-red-500">*</span></label>
                    <input type="text" name="pan_number" v-model="form.pan_number" :required="!form.gst_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter PAN Number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">CIN Number</label>
                    <input type="text" name="cin_number" v-model="form.cin_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter CIN Number" />
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