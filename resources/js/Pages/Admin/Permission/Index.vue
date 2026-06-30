<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  permissions: {
    type: Array,
    required: true
  }
});

const form = ref({
  name: ""
});

const columns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { 
    data: 'name',
    title: 'Permission Name',
    render: (data) => `<span class="capitalize font-semibold text-gray-700">${data}</span>`
  },
  {
    title: 'Actions',
    data: null,
    render: (data) => {
      return `
        <div class="flex gap-2">
          <button class="text-white bg-blue-600 hover:bg-blue-800 px-3 py-1 rounded edit-btn" data-id="${data.id}" data-name="${data.name}"><i class="fa fa-edit"></i></button>
          <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
        </div>
      `;
    }
  }
];

onMounted(() => {
  setupEditButton();
  setupDeleteButton();
});

const submitForm = async () => {
  if (!form.value.name.trim()) {
    toast.error("Permission Name is required!");
    return;
  }

  try {
    const response = await axios.post('/permission/store', form.value);
    toast.success(response.data.message || "Permission created successfully!");
    form.value.name = "";
    setTimeout(() => {
      location.reload();
    }, 1000);
  } catch (error) {
    const errorMessage =
      error.response?.data?.message || "Failed to create permission.";
    toast.error(errorMessage);
  }
};

function setupEditButton() {
  document.addEventListener('click', handleEditClick);
}

function handleEditClick(event) {
  const button = event.target.closest('.edit-btn');
  if (button) {
    const id = button.dataset.id;
    const name = button.dataset.name;
    editPermission(id, name);
  }
}

function editPermission(id, oldName) {
  Swal.fire({
    title: 'Rename Permission',
    html: '<div style="text-align: left; font-weight: 600; font-size: 14px; margin-bottom: 8px;">Permission Name <span style="color: red;">*</span></div>',
    input: 'text',
    inputValue: oldName,
    showCancelButton: true,
    confirmButtonColor: '#2e2c92',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Save Changes',
    inputValidator: (value) => {
      if (!value || !value.trim()) {
        return 'Permission name cannot be empty!';
      }
    }
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(`/permission/update/${id}`, { name: result.value })
        .then((response) => {
          Swal.fire('Updated!', response.data.message || 'Permission updated.', 'success');
          location.reload();
        })
        .catch((error) => {
          const msg = error.response?.data?.message || 'Failed to update permission.';
          Swal.fire('Error!', msg, 'error');
        });
    }
  });
}

function setupDeleteButton() {
  document.addEventListener('click', handleDeleteClick);
}

function handleDeleteClick(event) {
  const button = event.target.closest('.delete-btn');
  if (button) {
    const id = button.dataset.id;
    deletePermission(id);
  }
}

function deletePermission(id) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this permission? It will be removed from all roles.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#2e2c92',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/permission/destroy/${id}`)
        .then((response) => {
          Swal.fire('Deleted!', response.data.message || 'Permission deleted.', 'success');
          location.reload();
        })
        .catch((error) => {
          const msg = error.response?.data?.message || 'Failed to delete permission.';
          Swal.fire('Error!', msg, 'error');
        });
    }
  });
}
</script>

<template>
  <Head title="Permissions Management">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6 max-w-5xl mx-auto space-y-8">
      
      <!-- Page Header -->
      <div class="flex items-center justify-between">
        <h1 class="text-3xl font-bold text-gray-800">Permissions Management</h1>
      </div>

      <!-- Quick Add Form -->
      <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 space-y-4">
        <h2 class="text-lg font-bold text-[#292688]">Quick Add Permission</h2>
        <form @submit.prevent="submitForm" class="flex flex-col sm:flex-row gap-4 items-end sm:items-center">
          <div class="flex-1 w-full">
            <label class="block text-gray-700 font-semibold mb-2">Permission Name <span class="text-red-500">*</span></label>
            <input 
              type="text" 
              v-model="form.name" 
              required
              class="w-full px-4 py-3 bg-white text-black placeholder-gray-400 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
              placeholder="e.g. view reports" 
            />
          </div>
          <button 
            type="submit" 
            class="w-full sm:w-auto bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition shadow-md whitespace-nowrap"
          >
            + Add Permission
          </button>
        </form>
      </div>

      <!-- List Table -->
      <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
        <DataTable :data="permissions" :columns="columns" id="permission-table">
          <thead class="bg-[#2e2c92] text-white">
            <tr>
              <th scope="col">S No</th>
              <th scope="col">Permission Name</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
        </DataTable>
      </div>

    </div>
  </AuthenticatedLayout>
</template>
