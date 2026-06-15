<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';


defineProps({
  customers: {
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
    { data: 'name' },
    { data: 'email'},
    { data: 'phone'},
    {
      data: null,
      render: function (data) {
        const amount = data.due_amount || data.advance_amount || 0;
        const status = data.status;
        if (status === 'due') {
          return `<span style="color:red">- ₹${amount}</span>`;
        } else if (status === 'advance') {
          return `<span style="color:green">+ ₹${amount}</span>`;
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
            <div class="icon-all-dflex">
              <a href="/paymentsCustomer/${data.id}/history" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 2px 8px;" title="Payment History"><i class="fa fa-history"></i></a>
              <a  href="customer/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
              <a href="customer/${data.id}/download-pdf" class="btn btn-primary action-btn"><i class="fa fa-file-pdf-o"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 rounded action-btn delete-btn main-delete-button" style="padding: 2px 8px;" data-id="${data.id}"><i class="fa fa-trash"></i></button>
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
      const customerId = button.dataset.id;
      deleteCustomer(customerId);
    }
  });
}

function deleteCustomer(customerId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this customer?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/customer/destroy/${customerId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your customer has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the customer. Please try again.', 'error');
        });
    }
  });
}
</script>

<template>

    <Head title="Customer">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Customers</h1>
      <div class="flex items-center gap-4">
        <a :href="route('customer.Create')"
            class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
             <span>+ Add Customer</span>
        </a>
      </div>
    </div>
    <div class="overflow-x-auto mt-10">
      <!-- DataTable Component -->
      <DataTable :data="customers" :columns="columns" id="customer">
          <thead class="bg-[#2e2c92] text-white main-head-table">
              <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Volate</th>
                  <th scope="col">Action</th>
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
  </div>
    </AuthenticatedLayout>
</template>