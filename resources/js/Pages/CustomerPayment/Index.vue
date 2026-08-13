<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

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
      className: 'whitespace-nowrap',
      render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'name', title: 'Name', className: 'whitespace-nowrap' },
    { data: 'email', title: 'Email', className: 'whitespace-nowrap' },
    { data: 'phone', title: 'Phone', className: 'whitespace-nowrap' },
    {
      data: 'source',
      title: 'Source',
      className: 'whitespace-nowrap',
      render: function(data) {
        if (data === 'Sale') {
          return `<span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 whitespace-nowrap">Sale</span>`;
        } else if (data === 'Customer Payment' || data === 'Direct Payment') {
          return `<span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200 whitespace-nowrap">Direct Payment</span>`;
        } else if (data === 'Return') {
          return `<span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200 whitespace-nowrap">Return</span>`;
        } else {
          return `<span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 border border-gray-200 whitespace-nowrap">${data}</span>`;
        }
      }
    },
    {
      data: 'amount',
      title: 'Amount',
      className: 'whitespace-nowrap',
      render: function(data) {
        const val = parseFloat(data);
        if (val < 0) {
          return `<span style="color:#dc2626; font-weight:600;" class="whitespace-nowrap">- ₹${Math.abs(val).toFixed(2)}</span>`;
        } else {
          return `<span style="color:#16a34a; font-weight:600;" class="whitespace-nowrap">+ ₹${val.toFixed(2)}</span>`;
        }
      }
    },
    {
      data: 'payment_date',
      title: 'Payment Date',
      className: 'whitespace-nowrap',
      render: function(data) {
          return `<span class="whitespace-nowrap">${formatDate(data)}</span>`;
      }
    },
    { data: 'payment_method', title: 'Payment Method', className: 'whitespace-nowrap' },
    {
        title: 'Action',
        data: null,
        className: 'whitespace-nowrap',
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
            let sourceParam = data.source === 'Return' ? 'return' : 'payment';
            const phone = data.phone || row.phone || '';
            const whatsappBtn = phone
              ? `<button class="text-white bg-green-600 hover:bg-green-700 rounded whatsapp-statement-btn px-2 py-1" data-customer-id="${data.id}" data-phone="${phone}" title="Send Statement on WhatsApp" style="font-size:13px;"><i class="fa fa-whatsapp"></i></button>`
              : `<span class="text-gray-300 px-2" title="No phone number"><i class="fa fa-whatsapp"></i></span>`;
            return `
            <div class="flex gap-2 whitespace-nowrap">
              <a href="/paymentsCustomer/receipt/${sourceParam}/${data.transaction_id}" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" title="View Invoice"><i class="fa fa-eye"></i></a>
              <a href="/paymentsCustomer/${data.id}/history" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" title="View Statement"><i class="fa fa-list"></i></a>
              ${whatsappBtn}
            </div>
            `;
        }
    }
];

// DataTables configuration options
const dtOptions = {
  lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
  pageLength: 10,
  order: [[6, 'desc']], // Sort by payment date descending
  responsive: true,
};

onMounted(() => {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.whatsapp-statement-btn');
    if (button) {
      const customerId = button.dataset.customerId;
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
          axios.post(`/whatsapp/send-statement/${customerId}`)
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

    <Head title="Customer Payment">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
      <div class="p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Customer Payment</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage and track payments received from customers</p>
          </div>
          <div class="flex items-center gap-3">
            <a :href="route('paymentsCustomer.create')"
                class="bg-[#2e2c92] hover:bg-[#201d70] text-white px-4 py-2.5 rounded-xl font-semibold shadow-sm transition flex items-center gap-2 text-sm">
                 <i class="fa fa-plus"></i>
                 <span>Add Payment</span>
            </a>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
          <div class="overflow-x-auto">
            <!-- DataTable Component -->
            <DataTable :data="customers" :columns="columns" :options="dtOptions" id="customer-payment-table" class="w-full min-w-[850px]">
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
            </DataTable>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
</template>
