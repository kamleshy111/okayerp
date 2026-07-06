<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const form = ref({
  name: '',
  phone: '',
  email: '',
  notes: '',
});

const isSubmitting = ref(false);

const submitForm = () => {
  if (!form.value.name.trim()) {
    toast.error('Name is required');
    return;
  }
  isSubmitting.value = true;
  router.post(route('referral-user.store'), form.value, {
    onSuccess: () => {
      toast.success('Referral user created successfully');
    },
    onError: (errors) => {
      isSubmitting.value = false;
      Object.keys(errors).forEach((key) => {
        toast.error(errors[key]);
      });
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>

<template>
  <Head title="Add Referral User">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  </Head>
  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md max-w-2xl mx-auto space-y-6">
      <div class="flex items-center gap-2 text-sm">
        <a :href="route('referral-user.index')" class="text-indigo-600 hover:text-indigo-900 flex items-center gap-1">
          <i class="bi bi-arrow-left"></i> Back to Referral Users
        </a>
      </div>

      <div>
        <h2 class="text-2xl font-bold text-[#292688]">Add Referral User</h2>
        <p class="text-sm text-gray-500 mt-1">Create a new referral user entry</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
          <input
            type="text"
            v-model="form.name"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none"
            placeholder="Enter name"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
          <input
            type="text"
            v-model="form.phone"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none"
            placeholder="Enter phone number"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input
            type="email"
            v-model="form.email"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none"
            placeholder="Enter email address"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="4"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none"
            placeholder="Add any extra information about this referral user"
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <a
            :href="route('referral-user.index')"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-5 py-2 rounded-lg font-semibold transition"
          >
            Cancel
          </a>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="bg-[#292688] hover:bg-[#1e1d6a] text-white px-6 py-2 rounded-lg font-semibold shadow transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Saving...' : 'Save Referral User' }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
