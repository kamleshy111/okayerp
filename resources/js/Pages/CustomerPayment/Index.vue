<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  customers: {
        type: Array
    }
});

// Date formatter helper
const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
};

// Column definitions for DataTable
const columns = [
    {
      data: null,
      title: 'S No',
      render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'name', title: 'Name' },
    { data: 'email', title: 'Email' },
    { data: 'phone', title: 'Phone' },
    {
      data: 'source',
      title: 'Source',
      render: function(data) {
        if (data === 'Sale') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Sale</span>`;
        } else if (data === 'Customer Payment') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200">Direct Payment</span>`;
        } else if (data === 'Return') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200">Return</span>`;
        } else {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200">${data}</span>`;
        }
      }
    },
    {
      data: 'amount',
      title: 'Amount',
      render: function(data) {
        const val = parseFloat(data);
        if (val < 0) {
          return `<span style="color:#dc2626; font-weight:600;">- ₹${Math.abs(val).toFixed(2)}</span>`;
        } else {
          return `<span style="color:#16a34a; font-weight:600;">+ ₹${val.toFixed(2)}</span>`;
        }
      }
    },
    {
      data: 'payment_date',
      title: 'Payment Date',
      render: function(data) {
          return formatDate(data);
      }
    },
    { data: 'payment_method', title: 'Payment Method' },
    {
        title: 'Action',
        data: null,
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
            let sourceParam = data.source === 'Return' ? 'return' : 'payment';
            return `
            <div class="icon-all-dflex">
              <a href="/paymentsCustomer/receipt/${sourceParam}/${data.transaction_id}" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 2px 8px;" title="View Invoice"><i class="fa fa-eye"></i> View</a>
            </div>
            `;
        }
    }
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
                  <th scope="col">Source</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Payment Date</th>
                  <th scope="col">Payment Method</th>
                  <th scope="col">Action</th>
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
  </div>
  </AuthenticatedLayout>
</template>
