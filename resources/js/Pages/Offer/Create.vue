<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const form = ref({
  title: '',
  target_amount: '',
  start_date: new Date().toLocaleDateString('en-CA'),
  end_date: '',
  reward_description: '',
});

const isSubmitting = ref(false);

const submitForm = () => {
  if (!form.value.title.trim()) {
    toast.error('Offer Title is required');
    return;
  }
  if (!form.value.target_amount || parseFloat(form.value.target_amount) <= 0) {
    toast.error('Target amount must be a positive number');
    return;
  }
  if (!form.value.start_date || !form.value.end_date) {
    toast.error('Start and End dates are required');
    return;
  }

  isSubmitting.value = true;
  router.post(route('offer.store'), form.value, {
    onSuccess: () => {
      toast.success('Offer created successfully');
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
  <Head title="Create Target Offer">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  </Head>
  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md max-w-2xl mx-auto space-y-6">
      <div class="flex items-center gap-2 text-sm">
        <a :href="route('offer.index')" class="text-indigo-600 hover:text-indigo-900 flex items-center gap-1 font-semibold">
          <i class="bi bi-arrow-left"></i> Back to Offers list
        </a>
      </div>

      <div>
        <h2 class="text-2xl font-bold text-[#292688]">Create Target Offer</h2>
        <p class="text-sm text-gray-500 mt-1">Set a referral sales target and reward payouts</p>
      </div>

      <form @submit.prevent="submitForm" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Offer Title <span class="text-red-500">*</span></label>
          <input
            type="text"
            v-model="form.title"
            required
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
            placeholder="e.g. Summer Payout Bonus"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Target Sales Amount (₹) <span class="text-red-500">*</span></label>
          <input
            type="number"
            v-model="form.target_amount"
            required
            min="1"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
            placeholder="e.g. 50000"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
            <input
              type="date"
              v-model="form.start_date"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
            <input
              type="date"
              v-model="form.end_date"
              required
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Reward/Payout Description</label>
          <textarea
            v-model="form.reward_description"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white text-black"
            placeholder="Describe what reward they get, e.g. 5% Payout Bonus, Gift Voucher, etc."
          ></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <a
            :href="route('offer.index')"
            class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-5 py-2 rounded-lg font-semibold transition"
          >
            Cancel
          </a>
          <button
            type="submit"
            :disabled="isSubmitting"
            class="bg-[#292688] hover:bg-[#1e1d6a] text-white px-6 py-2 rounded-lg font-semibold shadow transition disabled:opacity-50"
          >
            {{ isSubmitting ? 'Creating...' : 'Create Offer' }}
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
