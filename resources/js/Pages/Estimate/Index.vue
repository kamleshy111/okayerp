<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
  estimates: {
        type: Array,
        required: true
    }
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
    text: `Do you want to delete ${selectedIds.value.length} selected quotations?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete them!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('bulk-delete'), {
        resource: 'estimate',
        ids: selectedIds.value
      })
      .then((response) => {
        const { succeeded, failed } = response.data;
        const totalFailed = Object.keys(failed).length;

        if (totalFailed > 0) {
          let failMessages = '';
          Object.entries(failed).forEach(([id, err]) => {
            failMessages += `Quotation #${id}: ${err}<br>`;
          });
          Swal.fire({
            icon: 'warning',
            title: 'Bulk Delete Complete with Errors',
            html: `Deleted ${succeeded.length} quotations.<br>${totalFailed} failed:<br><div style="text-align: left; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 12px; color: #b91c1c; max-height: 150px; overflow-y: auto;">${failMessages}</div>`,
            confirmButtonColor: '#2e2c92'
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Deleted!', 'Selected quotations deleted successfully.', 'success').then(() => {
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
  order: [[6, 'desc']], // Order by Estimate date by default
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
    { data: 'estimate_no', title: 'Estimate No' },
    { data: 'customer_name', title: 'Customer' },
    { data: 'customer_phone', title: 'Phone' },
    {
        data: 'grand_total',
        title: 'Grand Total',
        render: (data) => `₹ ${parseFloat(data || 0).toFixed(2)}`
    },
    {
        data: 'estimate_date',
        title: 'Estimate Date',
        render: (data) => data ? new Date(data).toLocaleDateString('en-GB').replace(/\//g, '-') : '-'
    },
    {
        data: 'expiry_date',
        title: 'Expiry Date',
        render: (data) => data ? new Date(data).toLocaleDateString('en-GB').replace(/\//g, '-') : '-'
    },
    {
        data: 'status',
        title: 'Status',
        render: (data) => {
            let badgeClass = 'bg-gray-100 text-gray-800';
            if (data === 'Draft') badgeClass = 'bg-yellow-100 text-yellow-800';
            else if (data === 'Sent') badgeClass = 'bg-blue-100 text-blue-800';
            else if (data === 'Accepted') badgeClass = 'bg-green-100 text-green-800';
            else if (data === 'Invoiced') badgeClass = 'bg-purple-100 text-purple-800';
            else if (data === 'Declined') badgeClass = 'bg-red-100 text-red-800';
            else if (data === 'Expired') badgeClass = 'bg-orange-100 text-orange-800';
            return `<span class="px-2.5 py-1 rounded-full text-xs font-semibold ${badgeClass}">${data}</span>`;
        }
    },
    {
        title: 'Actions',
        data: null,
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
            let convertBtn = '';
            if (data.status !== 'Invoiced') {
                convertBtn = `
                <a href="/sale/create?estimate_id=${data.id}" class="text-white bg-green-600 hover:bg-green-700 rounded action-btn text-xs font-medium inline-flex items-center gap-1" title="Convert to Sale" style="padding: 8px 10px;">
                    <i class="fa fa-exchange"></i> Convert
                </a>`;
            } else {
                convertBtn = `<span class="bg-purple-50 text-purple-700 border border-purple-200 rounded text-xs font-medium" style="padding: 8px 10px;">Invoiced</span>`;
            }
            return `
            <div class="flex items-center gap-2">
              <a href="/estimate/${data.id}/edit" class="btn btn-primary action-btn px-2.5 py-1 rounded bg-[#2e2c92] hover:bg-[#201e6a] text-white"><i class="fa fa-edit"></i></a>
              <a href="/estimate/${data.id}/download-pdf" target="_blank" class="btn btn-primary action-btn px-2.5 py-1 rounded bg-[#2e2c92] hover:bg-[#201e6a] text-white"><i class="fa fa-file-pdf-o"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-2.5 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
              ${convertBtn}
            </div>
            `;
        }
    }
];

onMounted(() => {
  setupDeleteButton();
  setupBulkDeleteListeners();
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const estimateId = button.dataset.id;
      deleteEstimate(estimateId);
    }
  });
}

function deleteEstimate(estimateId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this quotation?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/estimate/destroy/${estimateId}`)
        .then((response) => {
          Swal.fire('Deleted!', response.data.message || 'Your quotation has been deleted.', 'success');
          location.reload();
        })
        .catch((err) => {
          Swal.fire('Error!', err.response?.data?.message || 'Failed to delete the quotation.', 'error');
        });
    }
  });
}
</script>

<template>
    <Head title="Estimates & Quotations">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <span class="flex items-center gap-2">
            <h1 class="text-3xl font-bold text-[#2e2c92] sm:block hidden">Quotations /</h1>
            <h1 class="text-3xl font-bold text-[#2e2c92]"> Estimates</h1>
          </span>
          <div class="flex items-center gap-4">
            <a :href="route('estimate.create')" class="hover:bg-[#2e2c92] hover:text-white border border-[#2e2c92] text-[#2e2c92] px-4 py-2 rounded-lg font-medium transition duration-200">
                <span>+ Add</span>
            </a>
          </div>
        </div>
        <div class="overflow-x-auto mt-10">
          <DataTable :data="estimates" :columns="columns" :options="dtOptions" id="estimate">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
                      <th scope="col" style="width: 40px;"><input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
                      <th scope="col">S No</th>
                      <th scope="col">Estimate No</th>
                      <th scope="col">Customer</th>
                      <th scope="col">Phone</th>
                      <th scope="col">Grand Total</th>
                      <th scope="col">Estimate Date</th>
                      <th scope="col">Expiry Date</th>
                      <th scope="col">Status</th>
                      <th scope="col">Action</th>
                  </tr>
              </thead>
            </DataTable>
        </div>
      </div>

      <!-- Floating Bulk Action Bar -->
      <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white border border-gray-200 rounded-2xl shadow-xl px-6 py-4 flex items-center gap-6">
        <span class="text-sm font-semibold text-gray-700">{{ selectedIds.length }} quotations selected</span>
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
