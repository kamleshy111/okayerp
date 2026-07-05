<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

defineProps({
  stores: {
        type: Array
    }
})

// Column definitions for DataTable
const columns = [
    {
      data: null,
      title: 'S No',
      render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'name', title: 'Name' },
    { data: 'phone', title: 'Phone' },
    { data: 'email', title: 'Email' },
    {
        title: 'Actions',
        data: null,
        className: 'text-center',
        render: (data, type, row) => {
            return `
            <div class="flex gap-2 justify-center items-center">
              <button class="inline-flex items-center gap-1.5 text-white bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded-xl text-xs font-semibold action-btn edit-btn transition-colors duration-200 cursor-pointer shadow-sm hover:shadow" data-id="${data.id}" title="Edit Store">
                <i class="fa fa-edit"></i>
              </button>
              <button class="inline-flex items-center gap-1.5 text-white bg-red-600 hover:bg-red-700 px-3 py-2 rounded-xl text-xs font-semibold action-btn delete-btn transition-colors duration-200 cursor-pointer shadow-sm hover:shadow" data-id="${data.id}" title="Delete Store">
                <i class="fa fa-trash"></i>
              </button>
              <button class="inline-flex items-center gap-1.5 text-white bg-purple-600 hover:bg-purple-700 px-3 py-2 rounded-xl text-xs font-semibold action-btn permission-btn transition-colors duration-200 cursor-pointer shadow-sm hover:shadow" data-id="${data.id}" title="Permissions">
                <i class="fa fa-key"></i>
              </button>
              <button class="inline-flex items-center gap-1.5 text-white bg-emerald-600 hover:bg-emerald-700 px-3 py-2 rounded-xl text-xs font-semibold action-btn login-btn transition-colors duration-200 cursor-pointer shadow-sm hover:shadow" data-id="${data.id}" title="Login as Store">
                <i class="fa fa-sign-in"></i>
              </button>
            </div>
            `;
        }
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton();
  setupLoginButton();
  setupEditButton();
  setupPermissionButton();
});

function setupEditButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.edit-btn');
    if (button) {
      const storeId = button.dataset.id;
      router.get(`/store/edit/${storeId}`);
    }
  });
}


function setupLoginButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.login-btn')
    if (button) {
      const storeId = button.dataset.id

      router.post(`/store/switch/start/${storeId}`)
    }
  })
}

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const storeId = button.dataset.id;
      deleteStore(storeId);
    }
  });
}

function deleteStore(storeId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this store?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#2e2c92',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/store/destroy/${storeId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your stores has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the store. Please try again.', 'error');
        });
    }
  });
}

const isPermissionModalOpen = ref(false);
const permissionForm = ref({
  userId: null,
  userName: '',
  permissions: []
});
const allPermissions = ref([]);
const isLoadingPermissions = ref(false);
const isSavingPermissions = ref(false);

function setupPermissionButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.permission-btn');
    if (button) {
      const storeId = button.dataset.id;
      openPermissionModal(storeId);
    }
  });
}

async function openPermissionModal(userId) {
  isLoadingPermissions.value = true;
  isPermissionModalOpen.value = true;
  permissionForm.value.userId = userId;
  permissionForm.value.userName = 'Loading...';
  permissionForm.value.permissions = [];
  allPermissions.value = [];

  try {
    const response = await axios.get(`/store/permissions/${userId}`);
    permissionForm.value.userName = response.data.userName;
    allPermissions.value = response.data.permissions;
    permissionForm.value.permissions = response.data.userPermissions;
  } catch (error) {
    toast.error("Failed to load permissions.");
    isPermissionModalOpen.value = false;
  } finally {
    isLoadingPermissions.value = false;
  }
}

const submitPermissionForm = async () => {
  isSavingPermissions.value = true;
  try {
    const response = await axios.post(`/store/permissions/${permissionForm.value.userId}`, {
      permissions: permissionForm.value.permissions
    });
    toast.success(response.data.message || "Permissions updated successfully!");
    isPermissionModalOpen.value = false;
  } catch (error) {
    const errorMessage = error.response?.data?.message || "Failed to update permissions.";
    toast.error(errorMessage);
  } finally {
    isSavingPermissions.value = false;
  }
};

const selectAllPermissions = () => {
  permissionForm.value.permissions = allPermissions.value.map(p => p.name);
};

const clearAllPermissions = () => {
  permissionForm.value.permissions = [];
};
</script>

<template>

  <Head title="All Stores" >
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6 mx-auto space-y-8">
      <!-- Premium Page Header -->
      <div class="flex sm:items-center justify-between gap-4">
        <div class="space-y-1">
          <h1 class="text-3xl font-black tracking-tight text-gray-900">Stores</h1>
        </div>
        <div class="flex items-center gap-4">
          <a :href="route('store.add')"
              class="flex items-center gap-2 bg-[#2e2c92] text-white hover:bg-[#1d1b6a] px-5 py-2.5 rounded-xl font-semibold transition-all duration-300 shadow-md hover:shadow-lg hover:-translate-y-0.5">
              <i class="fa fa-plus text-xs"></i>
              <span>Add Store</span>
          </a>
        </div>
      </div>

      <!-- Table Card Container -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden p-6">
        <div class="overflow-x-auto">
          <!-- DataTable Component -->
          <DataTable :data="stores" :columns="columns" id="store" class="w-full text-sm text-left text-gray-600">
              <thead class="bg-gray-50 text-xs text-gray-700 uppercase border-b border-gray-200">
                  <tr>
                      <th scope="col" class="px-6 py-4 font-bold text-gray-900">S No</th>
                      <th scope="col" class="px-6 py-4 font-bold text-gray-900">Name</th>
                      <th scope="col" class="px-6 py-4 font-bold text-gray-900">Phone</th>
                      <th scope="col" class="px-6 py-4 font-bold text-gray-900">Email</th>
                      <th scope="col" class="px-6 py-4 font-bold text-gray-900 text-center">Action</th>
                  </tr>
              </thead>
          </DataTable>
        </div>
      </div>
    </div>

    <!-- Manage Permissions Modal Popup -->
    <div v-if="isPermissionModalOpen"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;"
         @click.self="isPermissionModalOpen = false">
      <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-2xl max-w-2xl w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <div class="space-y-1">
            <h2 class="text-2xl font-bold text-[#292688]">Manage Permissions</h2>
            <p class="text-sm text-gray-500 font-medium">Store: <span class="text-gray-800 font-semibold">{{ permissionForm.userName }}</span></p>
          </div>
          <button @click="isPermissionModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>

        <div v-if="isLoadingPermissions" class="flex flex-col items-center justify-center py-12 space-y-4">
          <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-[#2e2c92]"></div>
          <p class="text-sm text-gray-500 font-medium">Fetching store permissions...</p>
        </div>

        <form v-else @submit.prevent="submitPermissionForm" class="space-y-6">
          <div class="flex justify-between items-center pb-2">
            <label class="block text-gray-700 font-bold text-base">Assign Direct Permissions</label>
            <div class="flex gap-2">
              <button type="button" @click="selectAllPermissions"
                      class="px-3 py-1.5 text-xs font-semibold bg-[#2e2c92]/10 text-[#2e2c92] hover:bg-[#2e2c92]/20 rounded-lg transition cursor-pointer">
                Select All
              </button>
              <button type="button" @click="clearAllPermissions"
                      class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition cursor-pointer">
                Clear All
              </button>
            </div>
          </div>

          <div v-if="allPermissions.length === 0" class="text-gray-400 italic py-6 text-center">
            No permissions available in the system.
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[250px] sm:max-h-[350px] overflow-y-auto pr-1">
            <label
              v-for="permission in allPermissions"
              :key="permission.id"
              :class="[
                'flex items-center gap-3 p-4 border rounded-2xl transition cursor-pointer shadow-sm hover:shadow',
                permissionForm.permissions.includes(permission.name)
                  ? 'border-[#2e2c92] bg-indigo-50/30'
                  : 'border-gray-200 hover:bg-gray-50'
              ]"
            >
              <input
                type="checkbox"
                :value="permission.name"
                v-model="permissionForm.permissions"
                class="rounded border-gray-300 text-[#2e2c92] focus:ring-[#2e2c92] h-5 w-5 cursor-pointer"
              />
              <div class="flex flex-col">
                <span class="text-sm font-semibold capitalize text-gray-800">{{ permission.name.replace(' manage', '') }}</span>
                <span class="text-xs text-gray-400 font-medium font-mono">Permission: {{ permission.name }}</span>
              </div>
            </label>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <button type="button" @click="isPermissionModalOpen = false"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="isSavingPermissions"
                    class="px-5 py-2.5 bg-[#2e2c92] hover:bg-[#2e2c92e6] text-white font-semibold rounded-xl shadow-md transition disabled:opacity-50 flex items-center gap-2 cursor-pointer">
              <span v-if="isSavingPermissions" class="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white"></span>
              {{ isSavingPermissions ? 'Saving...' : 'Save Permissions' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
