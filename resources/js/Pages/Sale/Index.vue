<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
  sales: {
        type: Array
    }
})

// Column definitions for DataTable
const columns = [
//   { data: 'id', title: 'S No' },
    { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'customerName'},
    { data: 'phone'},
    { data: 'email'},
    { data: 'grand_total' },
    { data: 'sale_date'},
    {data: 'payment_status'},
    {
        title: 'Actions',
        data: null,
        orderable: false,
        searchable: false, 
        render: (data, type, row) => {
            return `
            <div class="icon-all-dflex">
              <a  href="sale/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
              <a href="sale/${data.id}/download-pdf" class="btn btn-primary action-btn"><i class="fa fa-file-pdf-o"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        }
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton()
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
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the sale. Please try again.', 'error');
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
          <DataTable :data="sales" :columns="columns" id="sale">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
                      <th scope="col">S No</th>
                      <th scope="col">Name</th>
                      <th scope="col">Phone</th>
                      <th scope="col">Email</th>
                      <th scope="col">Amount</th>
                      <th scope="col">Sale Date</th>
                      <th scope="col">Payment Status</th>
                      <th scope="col">Action</th>
                  </tr>
              </thead>
              <!-- The table rows would be dynamically inserted here -->
            </DataTable>
        </div>
      </div>

    </AuthenticatedLayout>
</template>