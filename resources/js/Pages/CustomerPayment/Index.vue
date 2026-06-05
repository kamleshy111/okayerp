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
    { data: 'amount'},
    {  data: 'payment_date',
        render: function(data) {
            if (!data) return '';
            const date = new Date(data);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        }
    },
    { data: 'payment_method'},
];
</script>

<template>

    <Head title="Customer Payment">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Customer Payment</h1>
      <div class="flex items-center gap-4">
        <a :href="route('paymentsCustomer.create')"
            class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
             <span>+ Add Payment</span>
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
                  <th scope="col">Amount</th>
                  <th scope="col">Payment Date</th>
                  <th scope="col">Payment Method</th>
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
  </div>
    </AuthenticatedLayout>
</template>