<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

defineProps({
  categories: {
    type: Array
  }
});

// Modal State
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);

// Forms State
const form = ref({
  name: "",
  description: "",
  status: "active"
});

const editForm = ref({
  id: null,
  name: "",
  description: "",
  status: "active"
});

const selectedIds = ref([]);

const clearSelection = () => {
  selectedIds.value = [];
  const checkboxes = document.querySelectorAll('.row-checkbox, #select-all-rows');
  checkboxes.forEach(cb => { cb.checked = false; });
};

const setupBulkDeleteListeners = () => {
  document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'select-all-rows') {
      const checkboxes = document.querySelectorAll('.row-checkbox');
      checkboxes.forEach(cb => {
        cb.checked = event.target.checked;
        const id = parseInt(cb.dataset.id);
        if (event.target.checked) {
          if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
        } else {
          selectedIds.value = selectedIds.value.filter(x => x !== id);
        }
      });
    } else if (event.target && event.target.classList.contains('row-checkbox')) {
      const id = parseInt(event.target.dataset.id);
      if (event.target.checked) {
        if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
      } else {
        selectedIds.value = selectedIds.value.filter(x => x !== id);
      }
      const allCb = document.getElementById('select-all-rows');
      if (allCb) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        allCb.checked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
      }
    }
  });
};

const bulkDelete = () => {
  if (selectedIds.value.length === 0) return;

  Swal.fire({
    title: 'Are you sure?',
    text: `Do you want to delete ${selectedIds.value.length} selected income categories?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete them!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('bulk-delete'), {
        resource: 'income-category',
        ids: selectedIds.value
      })
      .then((response) => {
        const { succeeded, failed } = response.data;
        const totalFailed = Object.keys(failed).length;

        if (totalFailed > 0) {
          let failMessages = '';
          Object.entries(failed).forEach(([id, err]) => {
            failMessages += `Category #${id}: ${err}<br>`;
          });
          Swal.fire({
            icon: 'warning',
            title: 'Bulk Delete Complete with Errors',
            html: `Deleted ${succeeded.length} income categories.<br>${totalFailed} failed:<br><div style="text-align: left; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 12px; color: #b91c1c; max-height: 150px; overflow-y: auto;">${failMessages}</div>`,
            confirmButtonColor: '#2e2c92'
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Deleted!', 'Selected income categories deleted successfully.', 'success').then(() => {
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

// DataTables configuration options
const dtOptions = {
  lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
  pageLength: 10,
  order: [[2, 'asc']], // Order by name by default
  drawCallback: function() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
      const id = parseInt(cb.dataset.id);
      cb.checked = selectedIds.value.includes(id);
    });
    const allCb = document.getElementById('select-all-rows');
    if (allCb) {
      const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
      allCb.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
    }
  }
};

// Column definitions for DataTable
const columns = [
  {
    data: null,
    title: '<input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">',
    orderable: false,
    searchable: false,
    width: '40px',
    render: (data) => {
      const isChecked = selectedIds.value.includes(data.id) ? 'checked' : '';
      return `<input type="checkbox" class="row-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" data-id="${data.id}" ${isChecked}>`;
    }
  },
  {
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { data: 'name', title: 'Name' },
  { data: 'description', title: 'Description' },
  { data: 'status', title: 'Status' },
  {
    title: 'Actions',
    data: null,
    render: (data, type, row) => {
      return `
      <div class="icon-all-dflex">
        <button class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn edit-btn" style="padding: 4px 8px;"
                data-id="${data.id}"
                data-name="${data.name}"
                data-description="${data.description || ''}"
                data-status="${data.status}">
          <i class="fa fa-edit"></i>
        </button>
        <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
      </div>
      `;
    }
  }
];

// Attach event when component is mounted
onMounted(() => {
  setupEditButton();
  setupDeleteButton();
  setupBulkDeleteListeners();
});

function setupEditButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.edit-btn');
    if (button) {
      const id = button.dataset.id;
      const name = button.dataset.name;
      const description = button.dataset.description;
      const status = button.dataset.status;
      openEditModal(id, name, description, status);
    }
  });
}

function openEditModal(id, name, description, status) {
  editForm.value = {
    id: id,
    name: name,
    description: description,
    status: status
  };
  isEditModalOpen.value = true;
}

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const categoryId = button.dataset.id;
      deleteCategory(categoryId);
    }
  });
}

function deleteCategory(categoryId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this income category?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/income-category/destroy/${categoryId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your income category has been deleted.', 'success');
          location.reload();
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the income category. Please try again.', 'error');
        });
    }
  });
}

// Form Submit: Add Category
const submitAddForm = async () => {
  if (!form.value.name.trim()) {
    toast.error("Name is required.");
    return;
  }

  try {
    const response = await axios.post(`/income-category/store`, form.value);
    toast.success(response.data.message);
    isAddModalOpen.value = false;

    // Reset Form
    form.value = {
      name: "",
      description: "",
      status: "active"
    };

    setTimeout(() => {
      location.reload();
    }, 1000);
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

// Form Submit: Edit Category
const submitEditForm = async () => {
  if (!editForm.value.name.trim()) {
    toast.error("Name is required.");
    return;
  }

  try {
    const response = await axios.post(`/income-category/update/${editForm.value.id}`, editForm.value);
    toast.success(response.data.message);
    isEditModalOpen.value = false;

    setTimeout(() => {
      location.reload();
    }, 1000);
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};
</script>

<template>
  <Head title="Income Categories">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Income Categories</h1>
        <div class="flex items-center gap-4">
          <button @click="isAddModalOpen = true"
                  class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium transition cursor-pointer">
             <span>+ Add Income Category</span>
          </button>
        </div>
      </div>
      <div class="overflow-x-auto mt-10">
        <!-- DataTable Component -->
        <DataTable :data="categories" :columns="columns" :options="dtOptions" id="income-category">
          <thead class="bg-[#2e2c92] text-white main-head-table">
            <tr>
              <th scope="col" style="width: 40px;"><input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
              <th scope="col">S No</th>
              <th scope="col">Name</th>
              <th scope="col">Description</th>
              <th scope="col">Status</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
        </DataTable>
      </div>
    </div>

    <!-- Add Category Modal Popup -->
    <div v-if="isAddModalOpen"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;"
         @click.self="isAddModalOpen = false">
      <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <h2 class="text-2xl font-bold text-[#292688]">Add Income Category</h2>
          <button @click="isAddModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>

        <form @submit.prevent="submitAddForm" class="space-y-4">
          <div>
            <label class="block text-black font-semibold mb-2 text-sm">Name <span class="text-red-500">*</span></label>
            <input type="text" v-model="form.name" required
                   class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                   placeholder="Category Name" />
          </div>

          <div>
            <label class="block text-black font-semibold mb-2 text-sm">Status</label>
            <select v-model="form.status" required
                    class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <div>
            <label class="block text-black font-semibold mb-2 text-sm">Description</label>
            <textarea v-model="form.description" rows="3"
                      class="w-full px-4 py-3 bg-white text-black placeholder-gray-400 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                      placeholder="Enter details about this category..."></textarea>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <button type="button" @click="isAddModalOpen = false"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
              Cancel
            </button>
            <button type="submit"
                    class="px-5 py-2.5 bg-[#2e2c92] hover:bg-[#2e2c92e6] text-white font-semibold rounded-xl shadow-md transition">
              Save
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Category Modal Popup -->
    <div v-if="isEditModalOpen"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;"
         @click.self="isEditModalOpen = false">
      <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <h2 class="text-2xl font-bold text-[#292688]">Edit Income Category</h2>
          <button @click="isEditModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>

        <form @submit.prevent="submitEditForm" class="space-y-4">
          <div>
            <label class="block text-black font-semibold mb-2 text-sm">Name <span class="text-red-500">*</span></label>
            <input type="text" v-model="editForm.name" required
                   class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                   placeholder="Category Name" />
          </div>

          <div>
            <label class="block text-black font-semibold mb-2 text-sm">Status</label>
            <select v-model="editForm.status" required
                    class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>

          <div>
            <label class="block text-black font-semibold mb-2 text-sm">Description</label>
            <textarea v-model="editForm.description" rows="3"
                      class="w-full px-4 py-3 bg-white text-black placeholder-gray-400 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                      placeholder="Enter details about this category..."></textarea>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <button type="button" @click="isEditModalOpen = false"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
              Cancel
            </button>
            <button type="submit"
                    class="px-5 py-2.5 bg-[#2e2c92] hover:bg-[#2e2c92e6] text-white font-semibold rounded-xl shadow-md transition">
              Update
            </button>
          </div>
        </form>
      </div>
    </div>
    <!-- Floating Bulk Action Bar -->
    <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white border border-gray-200 rounded-2xl shadow-xl px-6 py-4 flex items-center gap-6">
      <span class="text-sm font-semibold text-gray-700">{{ selectedIds.length }} categories selected</span>
      <div class="flex gap-3">
        <button @click="bulkDelete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold text-sm flex items-center gap-2 transition-colors cursor-pointer shadow-sm">
          <i class="fa fa-trash"></i> Delete Selected
        </button>
        <button @click="clearSelection" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-semibold text-sm transition-colors cursor-pointer">
          Cancel
        </button>
      </div>
    </div>

  </AuthenticatedLayout>
</template>
