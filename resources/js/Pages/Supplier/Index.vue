<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
    suppliers: {
        type: Array
    }
})

const isViewModalOpen = ref(false);
const viewData = ref({});

function showSupplierDetails(supplierId) {
  axios.get(`/supplier/${supplierId}/show`)
    .then(response => {
      viewData.value = response.data;
      isViewModalOpen.value = true;
    })
    .catch(error => {
      toast.error("Failed to load supplier details.");
    });
}

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
        const advance = parseFloat(data.advance_amount) || 0;
        const due = parseFloat(data.due_amount) || 0;
        const net = advance - due;
        const formattedAmount = (Math.abs(net) % 1 !== 0) ? Math.abs(net).toFixed(2) : Math.abs(net).toString();
        if (net < 0) {
          return `<span style="color:red">- ₹${formattedAmount}</span>`;
        } else if (net > 0) {
          return `<span style="color:green">+ ₹${formattedAmount}</span>`;
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
              <button class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 4px 8px;" title="View Details" data-id="${data.id}"><i class="fa fa-eye"></i></button>
              <a href="/paymentSupplier/${data.id}/history" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;" title="Payment History"><i class="fa fa-history"></i></a>
              <a  href="supplier/${data.id}/edit" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 6px 8px;"><i class="fa fa-edit"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        },
        sortable: false,
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton();
  setupViewButton();
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const supplierId = button.dataset.id;
      deleteSupplier(supplierId);
    }
  });
}

function setupViewButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.view-btn');
    if (button) {
      const supplierId = button.dataset.id;
      showSupplierDetails(supplierId);
    }
  });
}

function deleteSupplier(supplierId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this supplier?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/supplier/destroy/${supplierId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your supplier has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the supplier. Please try again.', 'error');
        });
    }
  });
}
</script>

<template>

    <Head title="Supplier">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Supplier</h1>
      <div class="flex items-center gap-4">
        <a :href="route('supplier.Create')"
            class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
             <span>+ Add Supplier</span>
        </a>
      </div>
    </div>
    <div class="overflow-x-auto mt-10">
      <!-- DataTable Component -->
      <DataTable :data="suppliers" :columns="columns" id="supplier">
          <thead class="bg-[#2e2c92] text-white main-head-table">
              <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Name</th>
                  <th scope="col">Email</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Net Balance</th>
                  <th scope="col">Action</th>
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
  </div>
    </AuthenticatedLayout>

    <!-- View Supplier Modal Popup -->
    <div v-if="isViewModalOpen"
         class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
         style="z-index: 9999;"
         @click.self="isViewModalOpen = false">
      <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-2xl w-full my-auto transform transition-all duration-300 border border-gray-100 space-y-6">
        <div class="flex justify-between items-center pb-3 border-b border-gray-100">
          <h2 class="text-2xl font-bold text-[#292688]">Supplier Details</h2>
          <button @click="isViewModalOpen = false" class="text-gray-400 hover:text-gray-600 transition">
            <i class="fa fa-close text-xl"></i>
          </button>
        </div>

        <div class="space-y-6 text-black text-left overflow-y-auto max-h-[70vh]">
          <!-- General Details -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">General Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Full Name</span>
                <span class="text-base font-medium">{{ viewData.name || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Email</span>
                <span class="text-base font-medium break-all">{{ viewData.email || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Phone</span>
                <span class="text-base font-medium">{{ viewData.phone || '---' }}</span>
              </div>
            </div>
          </div>

          <!-- Tax Details -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Tax Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">GSTIN</span>
                <span class="text-base font-medium">{{ viewData.gstin || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">PAN Number</span>
                <span class="text-base font-medium">{{ viewData.pan_number || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">CIN Number</span>
                <span class="text-base font-medium">{{ viewData.cin_number || '---' }}</span>
              </div>
            </div>
          </div>

          <!-- Address Details -->
          <div>
            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-3">Address & Location</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
              <div class="col-span-2">
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Address</span>
                <span class="text-base font-medium">{{ viewData.address || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">City</span>
                <span class="text-base font-medium">{{ viewData.city || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">District</span>
                <span class="text-base font-medium">{{ viewData.district || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">State</span>
                <span class="text-base font-medium">{{ viewData.state || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">Country</span>
                <span class="text-base font-medium">{{ viewData.country || '---' }}</span>
              </div>
              <div>
                <span class="block text-gray-500 text-xs font-semibold uppercase tracking-wider">PIN Code</span>
                <span class="text-base font-medium">{{ viewData.pin_code || '---' }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-gray-100">
          <button type="button" @click="isViewModalOpen = false"
                  class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition">
            Close
          </button>
        </div>
      </div>
    </div>
</template>
