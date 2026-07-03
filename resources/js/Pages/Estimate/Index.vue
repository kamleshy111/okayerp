<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
  estimates: {
        type: Array,
        required: true
    }
});

const columns = [
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
            <div class="icon-all-dflex flex items-center gap-2">
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
          <DataTable :data="estimates" :columns="columns" id="estimate">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
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
    </AuthenticatedLayout>
</template>
