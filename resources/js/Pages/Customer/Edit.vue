<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import vSelect from 'vue3-select';
import 'vue3-select/dist/vue3-select.css';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';

import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import axios from 'axios';


// Access props
const { customerDetail } = usePage().props;
const nameInput = ref(null);

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

// Move focus to the next logical input/select/button
const moveToNextInput = (event) => {
  const container = document.querySelector('.bg-white.p-8');
  if (!container) return;

  const elements = Array.from(container.querySelectorAll(
    'input:not([disabled]), select:not([disabled]), button:not([disabled]), .vs__search'
  )).filter(el => {
    const rect = el.getBoundingClientRect();
    const isVisible = rect.width > 0 && rect.height > 0;
    const isTrashBtn = el.querySelector('.bi-trash') || el.classList.contains('bg-red-600') || el.closest('button')?.classList.contains('bg-red-600') || el.querySelector('.bi-trash-fill') || el.closest('button')?.querySelector('.bi-trash-fill');
    const isAddRowBtn = el.closest('button')?.classList.contains('bg-green-600') || el.classList.contains('bg-green-600');
    return isVisible && !isTrashBtn && !isAddRowBtn;
  });

  const currentIndex = elements.indexOf(event.target);
  if (currentIndex !== -1 && currentIndex < elements.length - 1) {
    event.preventDefault();
    elements[currentIndex + 1].focus();
  }
};

onMounted(() => {
    nextTick(() => {
        if (nameInput.value) {
            nameInput.value.focus();
        }
    });
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
        
        // Auto-focus District dropdown
        nextTick(() => {
            const dropdowns = document.querySelectorAll('.v-select .vs__search');
            if (dropdowns.length > 1) {
                dropdowns[1].focus();
            }
        });
    }
});

watch(() => form.value.district, (newVal) => {
    if (newVal) {
        // Auto-focus City input
        nextTick(() => {
            const cityInput = document.querySelector('input[name="city"]');
            if (cityInput) {
                cityInput.focus();
            }
        });
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
                    <input ref="nameInput" type="text" v-model="form.name" name="name" required
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email</label>
                    <input type="email" v-model="form.email" name="email"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Phone</label>
                    <input type="number" v-model="form.phone" name="phone"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Phone" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">GST Number</label>
                    <input type="text" v-model="form.gst_number" name="gst_number"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="GST Number" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" v-model="form.address" name="address"
                        @keydown.enter.prevent="moveToNextInput"
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
                        @keydown.enter="moveToNextInput"
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
                        @keydown.enter="moveToNextInput"
                    ></v-select>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">City</label>
                    <input type="text" name="city" v-model="form.city"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter City" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Country</label>
                    <select name="country" v-model="form.country"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Country</option>
                        <option v-for="c in $page.props.countries" :key="c" :value="c">
                            {{ c }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">PIN Code</label>
                    <input type="text" v-model="form.pin_code" name="pin_code"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="PIN Code" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">PAN Number</label>
                    <input type="text" v-model="form.pan_number" name="pan_number"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter PAN Number" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">CIN Number</label>
                    <input type="text" v-model="form.cin_number" name="cin_number"
                        @keydown.enter.prevent="moveToNextInput"
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