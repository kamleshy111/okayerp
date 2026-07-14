<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

defineProps({
  suppliers: {
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
        if (data === 'Purchase') {
          return `<span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">Purchase</span>`;
        } else if (data === 'Supplier Payment') {
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
            const phone = data.phone || row.phone || '';
            const whatsappBtn = phone
              ? `<button class="text-white bg-green-600 hover:bg-green-700 rounded whatsapp-statement-btn px-2 py-1" data-supplier-id="${data.id}" data-phone="${phone}" title="Send Statement on WhatsApp" style="font-size:13px;"><i class="fa fa-whatsapp"></i></button>`
              : `<span class="text-gray-300 px-2" title="No phone number"><i class="fa fa-whatsapp"></i></span>`;
            return `
            <div class="flex gap-2">
              <a href="/paymentSupplier/${data.id}/history" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" title="View Statement"><i class="fa fa-list"></i></a>
              ${whatsappBtn}
            </div>
            `;
        }
    }
];

onMounted(() => {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.whatsapp-statement-btn');
    if (button) {
      const supplierId = button.dataset.supplierId;
      const phone = button.dataset.phone;
      Swal.fire({
        title: 'Send Statement on WhatsApp?',
        text: `Send account statement to ${phone}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fa fa-whatsapp"></i> Yes, Send!',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({ title: 'Sending...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
          axios.post(`/whatsapp/send-supplier-statement/${supplierId}`)
            .then((response) => {
              Swal.fire('Sent!', response.data.message, 'success');
            })
            .catch((error) => {
              Swal.fire('Error', error.response?.data?.message || 'Failed to send.', 'error');
            });
        }
      });
    }
  });
});
</script>

<template>

    <Head title="Supplier Payment">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Supplier Payment</h1>
      <div class="flex items-center gap-4">
        <a :href="route('paymentSupplier.create')"
            class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
             <span>+ Add Payment</span>
        </a>
      </div>
    </div>
    <div class="overflow-x-auto mt-10">
      <!-- DataTable Component -->
      <DataTable :data="suppliers" :columns="columns" id="customer">
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
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
  </div>
  </AuthenticatedLayout>
</template>