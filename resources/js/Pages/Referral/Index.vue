<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
  referralUsers: {
    type: Array,
    default: () => [],
  },
});

const referralUsers = ref([...props.referralUsers]);

const deleteReferral = async (id) => {
  if (!confirm('Are you sure you want to delete this referral user?')) return;
  try {
    await axios.delete(`/referral-user/destroy/${id}`);
    referralUsers.value = referralUsers.value.filter(r => r.id !== id);
    toast.success('Referral user deleted successfully.');
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to delete referral user.');
  }
};
</script>

<template>
  <Head title="Referral Users">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  </Head>
  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-[#292688]">Referral Users</h2>
          <p class="text-sm text-gray-500 mt-1">Manage users who refer sales to your store</p>
        </div>
        <a
          :href="route('referral-user.create')"
          class="inline-flex items-center gap-2 bg-[#292688] hover:bg-[#1e1d6a] text-white px-5 py-2.5 rounded-lg font-semibold shadow transition"
        >
          <i class="bi bi-person-plus-fill"></i> Add Referral User
        </a>
      </div>

      <!-- Table -->
      <div v-if="referralUsers.length > 0" class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full table-auto text-sm">
          <thead class="bg-[#292688] text-white">
            <tr>
              <th class="px-5 py-3 text-left">#</th>
              <th class="px-5 py-3 text-left">Name</th>
              <th class="px-5 py-3 text-left">Phone</th>
              <th class="px-5 py-3 text-left">Email</th>
              <th class="px-5 py-3 text-left">Total Sales</th>
              <th class="px-5 py-3 text-left">Total Amount</th>
              <th class="px-5 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(user, index) in referralUsers"
              :key="user.id"
              class="border-t border-gray-100 hover:bg-indigo-50 transition"
            >
              <td class="px-5 py-3 text-gray-500">{{ index + 1 }}</td>
              <td class="px-5 py-3 font-semibold text-gray-800">{{ user.name }}</td>
              <td class="px-5 py-3 text-gray-600">{{ user.phone || '—' }}</td>
              <td class="px-5 py-3 text-gray-600">{{ user.email || '—' }}</td>
              <td class="px-5 py-3">
                <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                  {{ user.sales_count ?? 0 }}
                </span>
              </td>
              <td class="px-5 py-3 font-semibold text-green-700">
                ₹ {{ Number(user.referral_sales_sum_sale_amount ?? 0).toFixed(2) }}
              </td>
              <td class="px-5 py-3 flex items-center gap-2">
                <a
                  :href="route('referral-user.tracker', user.id)"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md text-xs font-semibold transition flex items-center gap-1"
                >
                  <i class="bi bi-bar-chart-steps"></i> Tracker
                </a>
                <a
                  :href="route('referral-user.edit', user.id)"
                  class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1.5 rounded-md text-xs font-semibold transition flex items-center gap-1"
                >
                  <i class="bi bi-pencil-fill"></i> Edit
                </a>
                <button
                  @click="deleteReferral(user.id)"
                  class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md text-xs font-semibold transition flex items-center gap-1"
                >
                  <i class="bi bi-trash-fill"></i> Delete
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-16 text-gray-400">
        <i class="bi bi-people text-5xl mb-4 block text-indigo-200"></i>
        <p class="text-lg font-medium">No referral users found.</p>
        <p class="text-sm mt-1">Click <strong>Add Referral User</strong> to get started.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
