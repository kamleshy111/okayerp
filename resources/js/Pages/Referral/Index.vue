<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
  referralUsers: {
    type: Array,
    default: () => [],
  },
});

const referralUsersList = ref([...props.referralUsers]);
const selectedIds = ref([]);
const selectAll = ref(false);
const pageSize = ref(10);
const currentPage = ref(1);

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedIds.value = referralUsersList.value.map(u => u.id);
  } else {
    selectedIds.value = [];
  }
};

const clearSelection = () => {
  selectedIds.value = [];
  selectAll.value = false;
};

const totalPages = computed(() => {
  if (pageSize.value === -1) return 1;
  return Math.ceil(referralUsersList.value.length / pageSize.value);
});

const paginatedUsers = computed(() => {
  if (pageSize.value === -1) return referralUsersList.value;
  const start = (currentPage.value - 1) * pageSize.value;
  return referralUsersList.value.slice(start, start + pageSize.value);
});

const changePage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

const deleteReferral = async (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this referral user?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await axios.delete(`/referral-user/destroy/${id}`);
        referralUsersList.value = referralUsersList.value.filter(r => r.id !== id);
        selectedIds.value = selectedIds.value.filter(sid => sid !== id);
        toast.success('Referral user deleted successfully.');
      } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to delete referral user.');
      }
    }
  });
};

const bulkDelete = () => {
  if (selectedIds.value.length === 0) return;

  Swal.fire({
    title: 'Are you sure?',
    text: `Do you want to delete ${selectedIds.value.length} selected referral users?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete them!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('bulk-delete'), {
        resource: 'referral-user',
        ids: selectedIds.value
      })
      .then((response) => {
        const { succeeded, failed } = response.data;
        const totalFailed = Object.keys(failed).length;

        if (totalFailed > 0) {
          let failMessages = '';
          Object.entries(failed).forEach(([id, err]) => {
            failMessages += `Referral User #${id}: ${err}<br>`;
          });
          Swal.fire({
            icon: 'warning',
            title: 'Bulk Delete Complete with Errors',
            html: `Deleted ${succeeded.length} referral users.<br>${totalFailed} failed:<br><div style="text-align: left; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 12px; color: #b91c1c; max-height: 150px; overflow-y: auto;">${failMessages}</div>`,
            confirmButtonColor: '#2e2c92'
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Deleted!', 'Selected referral users deleted successfully.', 'success').then(() => {
            location.reload();
          });
        }
      })
      .catch((error) => {
        Swal.fire('Error!', error.response?.data?.message || 'Failed to delete selected items.', 'error');
      });
    }
  });
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
        <div class="flex items-center gap-3">
          <a
            :href="route('referral-user.create')"
            class="inline-flex items-center gap-2 bg-[#292688] hover:bg-[#1e1d6a] text-white px-5 py-2.5 rounded-lg font-semibold shadow transition"
          >
            <i class="bi bi-person-plus-fill"></i> Add Referral User
          </a>
        </div>
      </div>

      <!-- Controls (Length selector) -->
      <div class="flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-100">
        <div class="flex items-center gap-2">
          <span class="text-sm font-medium text-gray-600">Show</span>
          <select v-model="pageSize" @change="currentPage = 1" class="rounded-lg border-gray-300 text-sm focus:ring-[#292688] focus:border-[#292688] py-1 px-3">
            <option :value="10">10 records</option>
            <option :value="25">25 records</option>
            <option :value="50">50 records</option>
            <option :value="100">100 records</option>
            <option :value="-1">All records</option>
          </select>
        </div>
        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Total: {{ referralUsersList.length }} entries</span>
      </div>

      <!-- Table -->
      <div v-if="referralUsersList.length > 0" class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full table-auto text-sm">
          <thead class="bg-[#292688] text-white">
            <tr>
              <th class="px-5 py-3 text-left w-[40px]">
                <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
              </th>
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
              v-for="(user, index) in paginatedUsers"
              :key="user.id"
              class="border-t border-gray-100 hover:bg-indigo-50 transition"
            >
              <td class="px-5 py-3 text-gray-500 w-[40px]">
                <input type="checkbox" :value="user.id" v-model="selectedIds" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
              </td>
              <td class="px-5 py-3 text-gray-500">{{ (currentPage - 1) * pageSize + index + 1 }}</td>
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

      <!-- Pagination Links -->
      <div v-if="totalPages > 1 && pageSize !== -1" class="flex justify-center items-center gap-2 py-4">
        <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm text-gray-600 disabled:opacity-50 transition cursor-pointer">
          &lt; Previous
        </button>
        <span class="text-sm font-semibold text-gray-600">Page {{ currentPage }} of {{ totalPages }}</span>
        <button @click="changePage(currentPage + 1)" :disabled="currentPage === totalPages" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm text-gray-600 disabled:opacity-50 transition cursor-pointer">
          Next &gt;
        </button>
      </div>

      <!-- Empty state -->
      <div v-else-if="referralUsersList.length === 0" class="text-center py-16 text-gray-400">
        <i class="bi bi-people text-5xl mb-4 block text-indigo-200"></i>
        <p class="text-lg font-medium">No referral users found.</p>
        <p class="text-sm mt-1">Click <strong>Add Referral User</strong> to get started.</p>
      </div>
    </div>

    <!-- Floating Bulk Action Bar -->
    <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white border border-gray-200 rounded-2xl shadow-xl px-6 py-4 flex items-center gap-6">
      <span class="text-sm font-semibold text-gray-700">{{ selectedIds.length }} referral users selected</span>
      <div class="flex gap-3">
        <button @click="bulkDelete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold text-sm flex items-center gap-2 transition-colors cursor-pointer shadow-sm">
          <i class="bi bi-trash-fill"></i> Delete Selected
        </button>
        <button @click="clearSelection" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-semibold text-sm transition-colors cursor-pointer">
          Cancel
        </button>
      </div>
    </div>

  </AuthenticatedLayout>
</template>
