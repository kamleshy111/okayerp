<script setup>
import { ref, computed, watch } from 'vue';
import vSelect from 'vue3-select';
import 'vue3-select/dist/vue3-select.css';
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
    pan_number: customerDetail.pan_number || '',
    cin_number: customerDetail.cin_number || '',
    city: customerDetail.city || '',
    district: customerDetail.district || '',
    state: customerDetail.state || '',
    country: customerDetail.country || '',
    pin_code: customerDetail.pin_code || '',
});

const page = usePage();

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
                    <label class="block text-black font-medium mb-2">Name <span class="text-red-500">*</span></label>
                    <input type="text" v-model="form.name" name="name" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email <span class="text-red-500">*</span></label>
                    <input type="email" v-model="form.email" name="email" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Phone <span class="text-red-500">*</span></label>
                    <input type="number" v-model="form.phone" name="phone" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Phone" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">GST Number <span v-if="!form.pan_number" class="text-red-500">*</span></label>
                    <input type="text" v-model="form.gst_number" name="gst_number" :required="!form.pan_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="GST Number" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" v-model="form.address" name="address"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="address" />
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
                    <v-select
                        :options="availableDistricts"
                        v-model="form.city"
                        placeholder="Search & Select City"
                        class="w-full"
                        :disabled="!form.state"
                    ></v-select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Country</label>
                    <input type="text" v-model="form.country" name="country"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Country" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">PIN Code</label>
                    <input type="text" v-model="form.pin_code" name="pin_code"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="PIN Code" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">PAN Number <span v-if="!form.gst_number" class="text-red-500">*</span></label>
                    <input type="text" v-model="form.pan_number" name="pan_number" :required="!form.gst_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter PAN Number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">CIN Number</label>
                    <input type="text" v-model="form.cin_number" name="cin_number"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter CIN Number" />
                </div>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Update</button>
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