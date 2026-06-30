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
const { storesDetail } = usePage().props;

const form = ref({
    name: storesDetail.name,
    phone: storesDetail.phone,
    email: storesDetail.email,
    address: storesDetail.address,
    profile_photo: null,
    bank_name: storesDetail.bank_name || "",
    account_number: storesDetail.account_number || "",
    ifsc_code: storesDetail.ifsc_code || "",
    branch_name: storesDetail.branch_name || "",
    gstin: storesDetail.gstin || "",
    city: storesDetail.city || "",
    district: storesDetail.district || "",
    state: storesDetail.state || "",
    country: storesDetail.country || "",
    pin_code: storesDetail.pin_code || "",
    pan_number: storesDetail.pan_number || "",
    cin_number: storesDetail.cin_number || "",
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

// preview state
const preview = ref(storesDetail.profile_photo ? `/storage/${storesDetail.profile_photo}` : null);

// handle file change
const handleFileUpload = (event) => {
    const file = event.target.files[0];
    form.value.profile_photo = file;

    if (file) {
        preview.value = URL.createObjectURL(file);
    }
};


// Form submit handler
const submitForm = async () => {
    try {
        const formData = new FormData();
        formData.append('name', form.value.name);
        formData.append('phone', form.value.phone);
        formData.append('email', form.value.email);
        formData.append('address', form.value.address);
        formData.append('bank_name', form.value.bank_name || '');
        formData.append('account_number', form.value.account_number || '');
        formData.append('ifsc_code', form.value.ifsc_code || '');
        formData.append('branch_name', form.value.branch_name || '');
        formData.append('gstin', form.value.gstin || '');
        formData.append('city', form.value.city || '');
        formData.append('district', form.value.district || '');
        formData.append('state', form.value.state || '');
        formData.append('country', form.value.country || '');
        formData.append('pin_code', form.value.pin_code || '');
        formData.append('pan_number', form.value.pan_number || '');
        formData.append('cin_number', form.value.cin_number || '');

        if (form.value.profile_photo) {
            formData.append('profile_photo', form.value.profile_photo);
        }

        const response = await axios.post(`/store/update/${storesDetail.id}`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        toast.success(response.data.message);

        setTimeout(() => {window.location.href = route('store'); }, 5000); 

    } catch (error) {
        const errorMessage = error.response?.data?.message || 'An error occurred. Please try again.';
        toast.error(errorMessage);
    }
};
</script>
<template>

    <Head title="Edit Store">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
      <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('store')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Stores</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Update Store</h2>
        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Name <span class="text-red-500">*</span></label>
                    <input type="text" v-model="form.name" name="name"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email <span class="text-red-500">*</span></label>
                    <input type="email" v-model="form.email" name="email" required
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Email" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Phone</label>
                    <input type="number" v-model="form.phone" name="phone"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="phone" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Address</label>
                    <input type="text" v-model="form.address" name="address"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Address" />
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
                <div>
                    <label class="block text-black font-medium mb-2">Profile Photo</label>
                    <input type="file" accept="image/*"  @change="handleFileUpload" class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"/>
                </div>
                <div>
                    <!-- Preview -->
                    <div v-if="preview" class="mt-3">
                        <img :src="preview" alt="Profile" class="w-20 h-20 rounded-full object-cover">
                    </div>
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