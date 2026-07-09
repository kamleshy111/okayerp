<script setup>
import { ref, nextTick, onMounted, computed, watch } from 'vue';
import vSelect from 'vue3-select';
import 'vue3-select/dist/vue3-select.css';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
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
    city: "",
    district: "",
    state: "",
    country: "",
    pin_code: "",
    pan_number: "",
    cin_number: "",
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

watch(() => form.value.country, (newVal) => {
    if (newVal && newVal !== 'India') {
        form.value.state = "";
        form.value.district = "";
    }
});

watch(() => form.value.state, (newVal, oldVal) => {
    if (oldVal !== undefined) {
        form.value.district = "";
        form.value.city = "";
    }
    if (newVal && (!form.value.country || form.value.country === 'India')) {
        form.value.country = "India";
    }
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
      city: "",
      district: "",
      state: "",
      country: "",
      pin_code: "",
      pan_number: "",
      cin_number: "",
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

    <Head title="Add Store">
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
                    <label class="block text-black font-medium mb-2">Name <span class="text-red-500">*</span></label>
                    <input  ref="nameInput" type="text" name="name" v-model="form.name" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter full name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" v-model="form.email" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter email" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Password <span class="text-red-500">*</span></label>
                    <input type="password" name="password" v-model="form.password" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter password" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Confirm Password <span class="text-red-500">*</span></label>
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
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" name="address" v-model="form.address"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter address" />
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
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">State</label>
                    <v-select
                        v-if="!form.country || form.country === 'India'"
                        :options="$page.props.gst_states"
                        label="display"
                        :reduce="state => state.name"
                        v-model="form.state"
                        placeholder="Search & Select State"
                        class="w-full"
                    ></v-select>
                    <input
                        v-else
                        type="text"
                        name="state"
                        v-model="form.state"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter State"
                    />
                </div>
                <div v-if="!form.country || form.country === 'India'">
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
                    <label class="block text-black font-medium mb-2">PIN Code</label>
                    <input type="text" name="pin_code" v-model="form.pin_code"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter PIN code" />
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

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-7">
                    <div>
                        <label class="block text-black font-medium mb-2">GSTIN</label>
                        <input type="text" name="gstin" v-model="form.gstin"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter GSTIN" />
                    </div>
                    <div>
                        <label class="block text-black font-medium mb-2">PAN Number</label>
                        <input type="text" name="pan_number" v-model="form.pan_number"
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