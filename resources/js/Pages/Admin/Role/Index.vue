<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  roles: {
    type: Array,
    required: true
  }
});

// Column definitions for DataTable
const columns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { 
    data: 'name',
    title: 'Role Name',
    render: (data) => `<span class="capitalize font-semibold">${data}</span>`
  },
  { 
    data: 'permissions',
    title: 'Permissions',
    render: (data) => {
      if (!data || data.length === 0) return '<span class="text-gray-400">None</span>';
      return `<div class="flex flex-wrap gap-1">
        ${data.map(p => `<span class="bg-blue-100 text-[#292688] text-xs px-2 py-0.5 rounded">${p}</span>`).join('')}
      </div>`;
    }
  },  
  {
    title: 'Actions',
    data: null,
    render: (data) => {
      const isSystem = ['admin', 'store'].includes(data.name.toLowerCase());
      if (isSystem) {
        return `
          <div class="flex gap-2">
            <button class="text-white bg-blue-600 hover:bg-blue-800 px-3 py-1 rounded edit-btn" data-id="${data.id}"><i class="fa fa-edit"></i> Edit Permissions</button>
            <span class="text-xs text-gray-500 flex items-center font-medium italic">System Protected</span>
          </div>
        `;
      }
      return `
        <div class="flex gap-2">
          <button class="text-white bg-blue-600 hover:bg-blue-800 px-3 py-1 rounded edit-btn" data-id="${data.id}"><i class="fa fa-edit"></i></button>
          <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
        </div>
      `;
    }
  }
];

// Attach events on mount
onMounted(() => {
  setupEditButton();
  setupDeleteButton();
});

function setupEditButton() {
  document.addEventListener('click', handleEditClick);
}

function handleEditClick(event) {
  const button = event.target.closest('.edit-btn');
  if (button) {
    const roleId = button.dataset.id;
    router.get(`/role/edit/${roleId}`);
  }
}

function setupDeleteButton() {
  document.addEventListener('click', handleDeleteClick);
}

function handleDeleteClick(event) {
  const button = event.target.closest('.delete-btn');
  if (button) {
    const roleId = button.dataset.id;
    deleteRole(roleId);
  }
}

function deleteRole(roleId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this role? This cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#2e2c92',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/role/destroy/${roleId}`)
        .then((response) => {
          Swal.fire('Deleted!', response.data.message || 'Role has been deleted.', 'success');
          location.reload();
        })
        .catch((error) => {
          const msg = error.response?.data?.message || 'Failed to delete role.';
          Swal.fire('Error!', msg, 'error');
        });
    }
  });
}
</script>

<template>
  <Head title="Roles Management">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Roles Management</h1>
        <div>
          <a :href="route('role.create')"
             class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium transition">
            <span>+ Add Role</span>
          </a>
        </div>
      </div>
      <div class="overflow-x-auto mt-10 bg-white p-6 rounded-xl shadow-md border border-gray-100">
        <!-- DataTable Component -->
        <DataTable :data="roles" :columns="columns" id="role-table">
          <thead class="bg-[#2e2c92] text-white">
            <tr>
              <th scope="col">S No</th>
              <th scope="col">Role Name</th>
              <th scope="col">Permissions</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
        </DataTable>
      </div>
    </div> 
  </AuthenticatedLayout>
</template>

<style>
/* Custom style for DataTable generated content */
.dt-container {
  padding: 10px 0;
}
</style>
