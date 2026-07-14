<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
  sales: {
        type: Array
    }
})

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
    text: `Do you want to delete ${selectedIds.value.length} selected sales?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete them!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('bulk-delete'), {
        resource: 'sale',
        ids: selectedIds.value
      })
      .then((response) => {
        const { succeeded, failed } = response.data;
        const totalFailed = Object.keys(failed).length;

        if (totalFailed > 0) {
          let failMessages = '';
          Object.entries(failed).forEach(([id, err]) => {
            failMessages += `Sale #${id}: ${err}<br>`;
          });
          Swal.fire({
            icon: 'warning',
            title: 'Bulk Delete Complete with Errors',
            html: `Deleted ${succeeded.length} sales.<br>${totalFailed} failed:<br><div style="text-align: left; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 12px; color: #b91c1c; max-height: 150px; overflow-y: auto;">${failMessages}</div>`,
            confirmButtonColor: '#2e2c92'
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Deleted!', 'Selected sales deleted successfully.', 'success').then(() => {
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
  order: [[6, 'desc']], // Order by Sale Date by default
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
      render: (data, type, row) => {
        if (!row.is_deletable) {
          return `<i class="fa fa-lock text-gray-400" title="Cannot delete: created more than 10 mins ago or has sales returns"></i>`;
        }
        const isChecked = selectedIds.value.includes(data.id) ? 'checked' : '';
        return `<input type="checkbox" class="row-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" data-id="${data.id}" ${isChecked}>`;
      }
    },
    {
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'customerName', title: 'Customer Name' },
    { data: 'phone', title: 'Phone' },
    { data: 'email', title: 'Email' },
    { data: 'grand_total',
        title: 'Amount',
        render: function (data) {
            return data ? '₹' + parseFloat(data).toFixed(2) : '₹0.00';
        }
    },
    { data: 'sale_date', title: 'Sale Date' },
    {
        title: 'Actions',
        data: null,
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
            let deleteBtn = '';
            if (row.is_deletable) {
                deleteBtn = `<button class="text-white bg-red-600 hover:bg-red-800 rounded action-btn delete-btn px-3 py-1" data-id="${data.id}"><i class="fa fa-trash"></i></button>`;
            }
            return `
            <div class="flex gap-2">
              <a href="sale/${data.id}" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" title="View Sale"><i class="fa fa-eye"></i></a>
              <a href="sale/${data.id}/download-pdf" class="btn btn-primary text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;"><i class="fa fa-file-pdf-o"></i></a>
              <a  href="sale/${data.id}/edit" class="btn btn-light text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;"><i class="fa fa-edit"></i></a>
              ${deleteBtn}
            </div>
            `;
        }
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton();
  setupBulkDeleteListeners();
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const saleId = button.dataset.id;
      deleteSale(saleId);
    }
  });
}

function deleteSale(saleId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this sale?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/sale/destroy/${saleId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your sale has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch((error) => {
          const errMsg = error.response?.data?.message || 'Failed to delete the sale. Please try again.';
          Swal.fire('Error!', errMsg, 'error');
        });
    }
  });
}

</script>

<template>

    <Head title="Sale">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-3xl font-bold">Sales</h1>
          <div class="flex items-center gap-4">
            <a :href="route('sale.create')" class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
                <span>+ Add Sale</span>
            </a>
          </div>
        </div>
        <div class="overflow-x-auto mt-10">
          <!-- DataTable Component -->
          <DataTable :data="sales" :columns="columns" :options="dtOptions" id="sale">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
                      <th scope="col" style="width: 40px;"><input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
                      <th scope="col">S No</th>
                      <th scope="col">Name</th>
                      <th scope="col">Phone</th>
                      <th scope="col">Email</th>
                      <th scope="col">Amount</th>
                      <th scope="col">Sale Date</th>
                      <th scope="col">Action</th>
                  </tr>
              </thead>
              <!-- The table rows would be dynamically inserted here -->
            </DataTable>
        </div>
      </div>

      <!-- Floating Bulk Action Bar -->
      <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white border border-gray-200 rounded-2xl shadow-xl px-6 py-4 flex items-center gap-6">
        <span class="text-sm font-semibold text-gray-700">{{ selectedIds.length }} sales selected</span>
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
