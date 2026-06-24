<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

const props = defineProps({
  incomes: {
    type: Array
  },
  categories: {
    type: Array,
    required: true
  }
});

// Modal State
const isAddModalOpen = ref(false);
const isEditModalOpen = ref(false);

const today = new Date().toISOString().split('T')[0];

// Forms State
const form = ref({
  income_category_id: "",
  received_from: "",
  amount: "",
  date: today,
  reference_no: "",
  description: ""
});

const editForm = ref({
  id: null,
  income_category_id: "",
  received_from: "",
  amount: "",
  date: "",
  reference_no: "",
  description: ""
});

// Column definitions for DataTable
const columns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { data: 'category_name', title: 'Category' },
  { data: 'received_from', title: 'Received From' },
  { data: 'amount', title: 'Amount', render: (data) => `₹${parseFloat(data).toFixed(2)}` },
  { data: 'date', title: 'Date' },
  { data: 'reference_no', title: 'Reference No' },
  { data: 'description', title: 'Description' },
  {
    title: 'Actions',
    data: null,
    render: (data, type, row) => {
      return `
      <div class="icon-all-dflex">
        <button class="btn btn-light action-btn edit-btn" 
                data-id="${data.id}" 
                data-category_id="${data.income_category_id}" 
                data-received_from="${data.received_from !== 'N/A' ? data.received_from : ''}" 
                data-amount="${data.amount}" 
                data-date="${data.date}" 
                data-reference_no="${data.reference_no !== 'N/A' ? data.reference_no : ''}" 
                data-description="${data.description || ''}">
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
});

function setupEditButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.edit-btn');
    if (button) {
      const id = button.dataset.id;
      const category_id = button.dataset.category_id;
      const received_from = button.dataset.received_from;
      const amount = button.dataset.amount;
      const date = button.dataset.date;
      const reference_no = button.dataset.reference_no;
      const description = button.dataset.description;
      openEditModal(id, category_id, received_from, amount, date, reference_no, description);
    }
  });
}

function openEditModal(id, category_id, received_from, amount, date, reference_no, description) {
  editForm.value = {
    id: id,
    income_category_id: category_id,
    received_from: received_from,
    amount: amount,
    date: date,
    reference_no: reference_no,
    description: description
  };
  isEditModalOpen.value = true;
}

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const incomeId = button.dataset.id;
      deleteIncome(incomeId);
    }
  });
}

function deleteIncome(incomeId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this income?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/income/destroy/${incomeId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your income has been deleted.', 'success');
          location.reload();
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the income. Please try again.', 'error');
        });
    }
  });
}

// Form Submit: Add Income
const submitAddForm = async () => {
  if (!form.value.income_category_id) {
    toast.error("Income Category is required.");
    return;
  }
  if (!form.value.amount || form.value.amount <= 0) {
    toast.error("Valid Amount is required.");
    return;
  }
  if (!form.value.date) {
    toast.error("Date is required.");
    return;
  }

  try {
    const response = await axios.post(`/income/store`, form.value);
    toast.success(response.data.message);
    isAddModalOpen.value = false;
    
    // Reset Form
    form.value = {
      income_category_id: "",
      received_from: "",
      amount: "",
      date: today,
      reference_no: "",
      description: ""
    };

    setTimeout(() => {
      location.reload();
    }, 1000);
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

// Form Submit: Edit Income
const submitEditForm = async () => {
  if (!editForm.value.income_category_id) {
    toast.error("Income Category is required.");
    return;
  }
  if (!editForm.value.amount || editForm.value.amount <= 0) {
    toast.error("Valid Amount is required.");
    return;
  }
  if (!editForm.value.date) {
    toast.error("Date is required.");
    return;
  }

  try {
    const response = await axios.post(`/income/update/${editForm.value.id}`, editForm.value);
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
  <Head title="Incomes">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Incomes</h1>
        <div class="flex items-center gap-4">
          <button @click="isAddModalOpen = true"
                  class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium transition cursor-pointer">
             <span>+ Add Income</span>
          </button>
        </div>
      </div>
      <div class="overflow-x-auto mt-10">
        <!-- DataTable Component -->
        <DataTable :data="incomes" :columns="columns" id="income">
          <thead class="bg-[#2e2c92] text-white main-head-table">
            <tr>
              <th scope="col">S No</th>
              <th scope="col">Category</th>
              <th scope="col">Received From</th>
              <th scope="col">Amount</th>
              <th scope="col">Date</th>
              <th scope="col">Reference No</th>
              <th scope="col">Description</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
        </DataTable>
      </div>
    </div> 

    <!-- Add Income Modal Popup -->
    <div v-if="isAddModalOpen" 
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6" 
         style="z-index: 9999;"
         @click.self="isAddModalOpen = false">
      <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-lg w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <h2 class="text-2xl font-bold text-[#292688]">Add Income</h2>
          <button @click="isAddModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>
        
        <form @submit.prevent="submitAddForm" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Category <span class="text-red-500">*</span></label>
              <select v-model="form.income_category_id" required
                      class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm">
                <option value="" disabled selected>Select Category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Received From</label>
              <input type="text" v-model="form.received_from"
                     class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                     placeholder="Payer Name" />
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Amount <span class="text-red-500">*</span></label>
              <input type="number" step="0.01" min="0" v-model="form.amount" required
                     class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                     placeholder="0.00" />
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Date <span class="text-red-500">*</span></label>
              <input type="date" v-model="form.date" required
                     class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm" />
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Reference No</label>
              <input type="text" v-model="form.reference_no"
                     class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                     placeholder="Receipt code, invoice no" />
            </div>

            <div class="sm:col-span-2">
              <label class="block text-black font-semibold mb-2 text-sm">Description</label>
              <textarea v-model="form.description" rows="3"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-400 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                        placeholder="Enter details about this income..."></textarea>
            </div>
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

    <!-- Edit Income Modal Popup -->
    <div v-if="isEditModalOpen" 
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6" 
         style="z-index: 9999;"
         @click.self="isEditModalOpen = false">
      <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-lg w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <h2 class="text-2xl font-bold text-[#292688]">Edit Income</h2>
          <button @click="isEditModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>
        
        <form @submit.prevent="submitEditForm" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Category <span class="text-red-500">*</span></label>
              <select v-model="editForm.income_category_id" required
                      class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm">
                <option value="" disabled>Select Category</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Received From</label>
              <input type="text" v-model="editForm.received_from"
                     class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                     placeholder="Payer Name" />
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Amount <span class="text-red-500">*</span></label>
              <input type="number" step="0.01" min="0" v-model="editForm.amount" required
                     class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                     placeholder="0.00" />
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Date <span class="text-red-500">*</span></label>
              <input type="date" v-model="editForm.date" required
                     class="w-full px-4 py-3 bg-white text-black border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm" />
            </div>

            <div>
              <label class="block text-black font-semibold mb-2 text-sm">Reference No</label>
              <input type="text" v-model="editForm.reference_no"
                     class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                     placeholder="Receipt code, invoice no" />
            </div>

            <div class="sm:col-span-2">
              <label class="block text-black font-semibold mb-2 text-sm">Description</label>
              <textarea v-model="editForm.description" rows="3"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-400 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm"
                        placeholder="Enter details about this income..."></textarea>
            </div>
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
  </AuthenticatedLayout>
</template>
