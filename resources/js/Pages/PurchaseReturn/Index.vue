<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
  returns: {
    type: Array,
    required: true
  }
});

// Column definitions for DataTable
const columns = [
  {
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { data: 'return_no', title: 'Return No' },
  {
    data: 'purchase_id',
    title: 'Original Bill',
    render: (data) => `Bill #${data}`
  },
  { data: 'supplierName', title: 'Supplier' },
  {
    data: 'refund_amount',
    title: 'Refund Amount',
    render: (data) => `₹ ${parseFloat(data).toFixed(2)}`
  },
  { data: 'refund_method', title: 'Refund Method' },
  { data: 'return_date', title: 'Return Date' },
  { data: 'reason', title: 'Reason' },
  {
    title: 'Action',
    data: null,
    orderable: false,
    searchable: false,
    render: (data) => `
      <div class="icon-all-dflex">
        <a href="purchase-return/${data.id}/download-pdf" class="btn btn-primary text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" target="_blank"><i class="fa fa-file-pdf-o"></i></a>
      </div>
    `
  }
];

</script>

<template>
  <Head title="Purchase Returns">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Purchase Returns</h1>
        <div class="flex items-center gap-4">
          <a :href="route('purchase-return.create')" class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
            <span>+ Add</span>
          </a>
        </div>
      </div>
      <div class="overflow-x-auto mt-10">
        <!-- DataTable Component -->
        <DataTable :data="returns" :columns="columns" id="purchase_return">
          <thead class="bg-[#2e2c92] text-white main-head-table">
            <tr>
              <th scope="col">S No</th>
              <th scope="col">Return No</th>
              <th scope="col">Original Bill</th>
              <th scope="col">Supplier</th>
              <th scope="col">Refund Amount</th>
              <th scope="col">Refund Method</th>
              <th scope="col">Return Date</th>
              <th scope="col">Reason</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
        </DataTable>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
