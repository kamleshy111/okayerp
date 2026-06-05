<script setup>
import { ref } from 'vue';
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

    <Head title="Customer">
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
                    <label class="block text-black font-medium mb-2">Name</label>
                    <input type="text" v-model="form.name" name="name"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Name" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Email</label>
                    <input type="text" v-model="form.email" name="email"
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

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Update</button>
            </div>

  </form>
</div>

    </AuthenticatedLayout>

</template>