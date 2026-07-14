<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
    suppliers: {
        type: Array
    }
})

const isViewModalOpen = ref(false);
const viewData = ref({});

function showSupplierDetails(supplierId) {
  axios.get(`/supplier/${supplierId}/show`)
    .then(response => {
      viewData.value = response.data;
      isViewModalOpen.value = true;
    })
    .catch(error => {
      toast.error("Failed to load supplier details.");
    });
}

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
    text: `Do you want to delete ${selectedIds.value.length} selected suppliers?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete them!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('bulk-delete'), {
        resource: 'supplier',
        ids: selectedIds.value
      })
      .then((response) => {
        const { succeeded, failed } = response.data;
        const totalFailed = Object.keys(failed).length;

        if (totalFailed > 0) {
          let failMessages = '';
          Object.entries(failed).forEach(([id, err]) => {
            failMessages += `Supplier #${id}: ${err}<br>`;
          });
          Swal.fire({
            icon: 'warning',
            title: 'Bulk Delete Complete with Errors',
            html: `Deleted ${succeeded.length} suppliers.<br>${totalFailed} failed:<br><div style="text-align: left; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 12px; color: #b91c1c; max-height: 150px; overflow-y: auto;">${failMessages}</div>`,
            confirmButtonColor: '#2e2c92'
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Deleted!', 'Selected suppliers deleted successfully.', 'success').then(() => {
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
    { data: 'email', title: 'Email' },
    { data: 'phone', title: 'Phone' },
    {
      data: null,
      title: 'Net Balance',
      render: function (data) {
        const advance = parseFloat(data.advance_amount) || 0;
        const due = parseFloat(data.due_amount) || 0;
        const net = advance - due;
        const formattedAmount = (Math.abs(net) % 1 !== 0) ? Math.abs(net).toFixed(2) : Math.abs(net).toString();
        if (net < 0) {
          return `<span style="color:red">- ₹${formattedAmount}</span>`;
        } else if (net > 0) {
          return `<span style="color:green">+ ₹${formattedAmount}</span>`;
        } else {
          return `<span style="color:green">₹0</span>`;
        }
      }
    },
    {
        title: 'Actions',
        data: null,
        render: (data, type, row) => {
            return `
            <div class="flex gap-2">
              <button class="view-btn text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 4px 8px;" title="View Details" data-id="${data.id}"><i class="fa fa-eye"></i></button>
              <a href="/paymentSupplier/${data.id}/history" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" title="Payment History"><i class="fa fa-history"></i></a>
              <a  href="supplier/${data.id}/edit" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;"><i class="fa fa-edit"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        },
        sortable: false,
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton();
  setupViewButton();
  setupBulkDeleteListeners();
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const supplierId = button.dataset.id;
      deleteSupplier(supplierId);
    }
  });
}

function setupViewButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.view-btn');
    if (button) {
      const supplierId = button.dataset.id;
      showSupplierDetails(supplierId);
    }
  });
}

function deleteSupplier(supplierId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this supplier?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/supplier/destroy/${supplierId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your supplier has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch((error) => {
          const errMsg = error.response?.data?.message || 'Failed to delete the supplier. Please try again.';
          Swal.fire('Error!', errMsg, 'error');
        });
    }
  });
}
</script>

<template>

    <Head title="Supplier">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Supplier</h1>
      <div class="flex items-center gap-4">
        <a :href="route('supplier.Create')"
            class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
             <span>+ Add Supplier</span>
        </a>
      </div>
    </div>
    <div class="overflow-x-auto mt-10">
      <!-- DataTable Component -->
      <DataTable :data="suppliers" :columns="columns" :options="dtOptions" id="supplier">
          <thead class="bg-[#2e2c92] text-white main-head-table">
              <tr>
                  <th scope="col" style="width: 40px;"><input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
                  <th scope="col">S No</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Net Balance</th>
                  <th scope="col">Action</th>
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
  </div>

  <!-- Floating Bulk Action Bar -->
  <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white border border-gray-200 rounded-2xl shadow-xl px-6 py-4 flex items-center gap-6">
    <span class="text-sm font-semibold text-gray-700">{{ selectedIds.length }} suppliers selected</span>
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

    <!-- View Supplier Modal Popup -->
    <div v-if="isViewModalOpen"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;"
         @click.self="isViewModalOpen = false">
      <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-2xl w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <h2 class="text-2xl font-bold text-[#292688]">Supplier Details</h2>
          <button @click="isViewModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>

        <div class="space-y-6 text-black text-left overflow-y-auto max-h-[70vh]">
          <!-- General Details -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">General Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Full Name</span>
                <span class="text-base font-medium">{{ viewData.name || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Email</span>
                <span class="text-base font-medium break-all">{{ viewData.email || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Phone</span>
                <span class="text-base font-medium">{{ viewData.phone || '---' }}</span>
              </div>
            </div>
          </div>

          <!-- Tax Details -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Tax Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">GSTIN</span>
                <span class="text-base font-medium">{{ viewData.gstin || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">PAN Number</span>
                <span class="text-base font-medium">{{ viewData.pan_number || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">CIN Number</span>
                <span class="text-base font-medium">{{ viewData.cin_number || '---' }}</span>
              </div>
            </div>
          </div>

          <!-- Address Details -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Address & Location</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <div class="col-span-2">
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Address</span>
                <span class="text-base font-medium">{{ viewData.address || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">City</span>
                <span class="text-base font-medium">{{ viewData.city || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">District</span>
                <span class="text-base font-medium">{{ viewData.district || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">State</span>
                <span class="text-base font-medium">{{ viewData.state || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Country</span>
                <span class="text-base font-medium">{{ viewData.country || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">PIN Code</span>
                <span class="text-base font-medium">{{ viewData.pin_code || '---' }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-100">
          <button type="button" @click="isViewModalOpen = false"
                  class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
            Close
          </button>
        </div>
      </div>
    </div>
</template>
