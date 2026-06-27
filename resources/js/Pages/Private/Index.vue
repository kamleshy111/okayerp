<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  unlocked: {
    type: Boolean,
    required: true,
  },
  hasPin: {
    type: Boolean,
    required: true,
  },
  sales: {
    type: Array,
    default: () => [],
  },
  purchases: {
    type: Array,
    default: () => [],
  },
  payments: {
    type: Array,
    default: () => [],
  },
  customers: {
    type: Array,
    default: () => [],
  },
});

const currentTab = ref('sales'); // 'sales', 'purchases', or 'payments'

// PIN code state
const pin = ref('');
const pinError = ref('');
const isSubmitting = ref(false);

const appendNumber = (num) => {
  if (pin.value.length < 4) {
    pin.value += num;
    if (pin.value.length === 4) {
      submitPin();
    }
  }
};

const deleteLast = () => {
  pin.value = pin.value.slice(0, -1);
};

const clearPin = () => {
  pin.value = '';
};

// Keyboard support
const handleKeyDown = (e) => {
  if (props.unlocked) return;
  if (e.key >= '0' && e.key <= '9') {
    appendNumber(e.key);
  } else if (e.key === 'Backspace') {
    deleteLast();
  } else if (e.key === 'Escape') {
    clearPin();
  }
};

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown);
  document.addEventListener('click', handleDeleteClick);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
  document.removeEventListener('click', handleDeleteClick);
});

const submitPin = async () => {
  if (pin.value.length !== 4) return;
  isSubmitting.value = true;
  pinError.value = '';
  
  try {
    const res = await axios.post(route('private.unlock'), { pin: pin.value });
    Swal.fire({
      icon: 'success',
      title: 'Ledger Unlocked',
      text: res.data.message,
      timer: 1200,
      showConfirmButton: false,
    });
    router.reload({ only: ['unlocked', 'sales', 'purchases', 'payments', 'customers'] });
  } catch (err) {
    pin.value = '';
    if (err.response && err.response.data && err.response.data.errors) {
      pinError.value = err.response.data.errors.pin[0];
    } else {
      pinError.value = 'Failed to unlock. Please try again.';
    }
  } finally {
    isSubmitting.value = false;
  }
};

const lockLedger = () => {
  router.post(route('private.lock'), {}, {
    onSuccess: () => {
      Swal.fire({
        icon: 'info',
        title: 'Ledger Locked',
        timer: 1000,
        showConfirmButton: false,
      });
    }
  });
};

// Payment Modal State
const showPaymentModal = ref(false);
const paymentForm = useForm({
  customer_id: '',
  amount: '',
  payment_date: new Date().toISOString().substring(0, 10),
  payment_method: 'Cash',
  note: '',
});

const submitPayment = () => {
  axios.post(route('private.payment.store'), paymentForm.data())
    .then((res) => {
      showPaymentModal.value = false;
      paymentForm.reset();
      Swal.fire('Success', res.data.message || 'Private payment recorded successfully.', 'success');
      router.reload();
    })
    .catch((err) => {
      if (err.response && err.response.data && err.response.data.message) {
        Swal.fire('Error', err.response.data.message, 'error');
      } else {
        Swal.fire('Error', 'Please correct the validation errors.', 'error');
      }
    });
};

// Totals
const totalPrivateSales = computed(() => {
  return props.sales.reduce((sum, item) => sum + parseFloat(item.grand_total || 0), 0);
});

const totalPrivatePurchases = computed(() => {
  return props.purchases.reduce((sum, item) => sum + parseFloat(item.grand_total || 0), 0);
});

const totalPrivatePayments = computed(() => {
  return props.payments.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
});

const pendingBalance = computed(() => {
  return totalPrivateSales.value - totalPrivatePayments.value;
});

// Datatable columns for sales
const saleColumns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { data: 'customerName', title: 'Customer' },
  { data: 'phone', title: 'Phone' },
  { 
    data: 'grand_total', 
    title: 'Amount',
    render: (data) => `₹${parseFloat(data).toFixed(2)}`
  },
  { data: 'sale_date', title: 'Date' },
  { data: 'payment_status', title: 'Payment Status' },
  {
    title: 'Actions',
    data: null,
    orderable: false,
    searchable: false, 
    render: (data, type, row) => {
      return `
      <div class="icon-all-dflex">
        <a href="sale/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
        <a href="sale/${data.id}/download-pdf" class="btn btn-primary action-btn"><i class="fa fa-file-pdf-o"></i></a>
        <button class="text-white bg-red-650 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
      </div>
      `;
    }
  }
];

// Datatable columns for payments
const paymentColumns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { data: 'name', title: 'Customer' },
  { data: 'phone', title: 'Phone' },
  { 
    data: 'amount', 
    title: 'Amount',
    render: (data) => `₹${parseFloat(data).toFixed(2)}`
  },
  { 
    data: 'payment_date', 
    title: 'Payment Date',
    render: function(data) {
      if (!data) return '';
      const date = new Date(data);
      const day = String(date.getDate()).padStart(2, '0');
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const year = date.getFullYear();
      return `${day}/${month}/${year}`;
    }
  },
  { data: 'payment_method', title: 'Method' },
  { data: 'note', title: 'Note', defaultContent: '--' }
];

// Datatable columns for purchases
const purchaseColumns = [
  { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
  },
  { data: 'supplierName', title: 'Supplier' },
  { data: 'phone', title: 'Phone' },
  { 
    data: 'grand_total', 
    title: 'Amount',
    render: (data) => `₹${parseFloat(data).toFixed(2)}`
  },
  { data: 'purchase_date', title: 'Date' },
  { data: 'payment_status', title: 'Payment Status' },
  {
    title: 'Actions',
    data: null,
    orderable: false,
    searchable: false, 
    render: (data, type, row) => {
      return `
      <div class="icon-all-dflex">
        <a href="purchase/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
        <button class="text-white bg-red-650 hover:bg-red-800 px-3 py-1 rounded action-btn delete-purchase-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
      </div>
      `;
    }
  }
];

const handleDeleteClick = (event) => {
  const button = event.target.closest('.delete-btn');
  if (button) {
    const saleId = button.dataset.id;
    Swal.fire({
      title: 'Are you sure?',
      text: 'Do you want to delete this private sale?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        axios.delete(`/sale/destroy/${saleId}`)
          .then(() => {
            Swal.fire('Deleted!', 'Private sale has been deleted.', 'success');
            router.reload();
          })
          .catch((error) => {
            const errMsg = error.response?.data?.message || 'Failed to delete sale.';
            Swal.fire('Error!', errMsg, 'error');
          });
      }
    });
  }

  const purchaseButton = event.target.closest('.delete-purchase-btn');
  if (purchaseButton) {
    const purchaseId = purchaseButton.dataset.id;
    Swal.fire({
      title: 'Are you sure?',
      text: 'Do you want to delete this private purchase?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
      if (result.isConfirmed) {
        axios.delete(`/purchase/destroy/${purchaseId}`)
          .then(() => {
            Swal.fire('Deleted!', 'Private purchase has been deleted.', 'success');
            router.reload();
          })
          .catch((error) => {
            const errMsg = error.response?.data?.message || 'Failed to delete purchase.';
            Swal.fire('Error!', errMsg, 'error');
          });
      }
    });
  }
};
</script>

<template>
  <Head title="Private Ledger">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  </Head>

  <AuthenticatedLayout>
    <div class="p-6 max-w-7xl mx-auto">
      
      <!-- 1. NOT CONFIGURED PIN STATE -->
      <div v-if="!hasPin" class="flex flex-col items-center justify-center min-h-[60vh] py-12">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 border border-gray-100 flex flex-col items-center">
          <div class="w-16 h-16 bg-yellow-50 rounded-full flex items-center justify-center text-yellow-500 mb-6">
            <i class="fa fa-exclamation-triangle text-3xl"></i>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 mb-2">No Security PIN Set</h2>
          <p class="text-sm text-gray-500 text-center mb-6">
            To view the Private Ledger, you must configure a 4-digit numeric Ledger PIN in your Profile settings first.
          </p>
          <a :href="route('profile.edit')" class="w-full text-center bg-[#2e2c92] hover:bg-[#1f1d6b] text-white font-medium py-3 px-4 rounded-xl shadow-lg transition-colors">
            Configure Ledger PIN
          </a>
        </div>
      </div>

      <!-- 2. LOCKED STATE -->
      <div v-else-if="!unlocked" class="flex flex-col items-center justify-center min-h-[60vh] py-12">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8 border border-gray-100 flex flex-col items-center">
          <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center text-red-500 mb-6 animate-pulse">
            <i class="fa fa-lock text-3xl"></i>
          </div>
          <h2 class="text-2xl font-bold text-gray-900 mb-2">Private Ledger Locked</h2>
          <p class="text-sm text-gray-500 text-center mb-6">
            Enter your 4-digit security PIN to access the isolated private ledger.
          </p>
          
          <!-- PIN Dots -->
          <div class="flex gap-4 mb-8">
            <div 
              v-for="i in 4" 
              :key="i"
              class="w-4 h-4 rounded-full border-2 border-gray-300 transition-all duration-150"
              :class="pin.length >= i ? 'bg-[#2e2c92] border-[#2e2c92] scale-110 shadow-md' : ''"
            ></div>
          </div>

          <!-- PIN Input Status -->
          <div v-if="pinError" class="text-sm text-red-600 mb-4 font-medium">{{ pinError }}</div>
          
          <!-- Numeric Keypad -->
          <div class="grid grid-cols-3 gap-4 w-full max-w-[280px]">
            <button 
              v-for="num in [1, 2, 3, 4, 5, 6, 7, 8, 9]" 
              :key="num"
              @click="appendNumber(num)"
              class="w-16 h-16 rounded-full bg-gray-50 hover:bg-gray-100 flex items-center justify-center text-xl font-semibold text-gray-700 transition-colors shadow-sm"
            >
              {{ num }}
            </button>
            <button 
              @click="clearPin"
              class="w-16 h-16 rounded-full flex items-center justify-center text-sm font-medium text-gray-500 hover:text-gray-700"
            >
              Clear
            </button>
            <button 
              @click="appendNumber(0)"
              class="w-16 h-16 rounded-full bg-gray-50 hover:bg-gray-100 flex items-center justify-center text-xl font-semibold text-gray-700 transition-colors shadow-sm"
            >
              0
            </button>
            <button 
              @click="deleteLast"
              class="w-16 h-16 rounded-full flex items-center justify-center text-sm font-medium text-gray-500 hover:text-gray-700"
            >
              <i class="fa fa-chevron-left"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- 3. UNLOCKED STATE -->
      <div v-else>
        <!-- Header -->
        <div class="flex items-center justify-between mb-8 pb-4 border-b border-gray-100">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Private Ledger</h1>
            <p class="text-sm text-gray-500 mt-1">
              Isolated cash transactions and payments.
            </p>
          </div>
          <button 
            @click="lockLedger"
            class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 px-4 py-2 rounded-lg font-medium transition-colors"
          >
            <i class="fa fa-lock"></i>
            <span>Lock Ledger</span>
          </button>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <!-- Sales Card -->
          <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Cash Sales</p>
              <h3 class="text-2xl font-bold text-gray-950 mt-1">₹{{ totalPrivateSales.toFixed(2) }}</h3>
            </div>
            <div class="w-12 h-12 bg-green-50 text-green-500 rounded-full flex items-center justify-center">
              <i class="fa fa-shopping-bag text-xl"></i>
            </div>
          </div>

          <!-- Purchases Card -->
          <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Cash Purchases</p>
              <h3 class="text-2xl font-bold text-gray-950 mt-1">₹{{ totalPrivatePurchases.toFixed(2) }}</h3>
            </div>
            <div class="w-12 h-12 bg-red-50 text-red-500 rounded-full flex items-center justify-center">
              <i class="fa fa-shopping-cart text-xl"></i>
            </div>
          </div>

          <!-- Payments Card -->
          <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Total Cash Payments</p>
              <h3 class="text-2xl font-bold text-gray-950 mt-1">₹{{ totalPrivatePayments.toFixed(2) }}</h3>
            </div>
            <div class="w-12 h-12 bg-indigo-50 text-[#2e2c92] rounded-full flex items-center justify-center">
              <i class="fa fa-money text-xl"></i>
            </div>
          </div>

          <!-- Balance Card -->
          <div class="bg-white rounded-xl shadow-md p-6 border border-gray-100 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Pending Book Balance</p>
              <h3 class="text-2xl font-bold mt-1" :class="pendingBalance >= 0 ? 'text-orange-500' : 'text-green-500'">
                ₹{{ pendingBalance.toFixed(2) }}
              </h3>
            </div>
            <div class="w-12 h-12 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center">
              <i class="fa fa-balance-scale text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex gap-4 border-b border-gray-200 mb-6">
          <button 
            @click="currentTab = 'sales'"
            class="pb-3 px-4 font-semibold text-sm transition-all border-b-2"
            :class="currentTab === 'sales' ? 'border-[#2e2c92] text-[#2e2c92]' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Cash Transactions ({{ sales.length }})
          </button>
          <button 
            @click="currentTab = 'purchases'"
            class="pb-3 px-4 font-semibold text-sm transition-all border-b-2"
            :class="currentTab === 'purchases' ? 'border-[#2e2c92] text-[#2e2c92]' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Cash Purchases ({{ purchases.length }})
          </button>
          <button 
            @click="currentTab = 'payments'"
            class="pb-3 px-4 font-semibold text-sm transition-all border-b-2"
            :class="currentTab === 'payments' ? 'border-[#2e2c92] text-[#2e2c92]' : 'border-transparent text-gray-500 hover:text-gray-700'"
          >
            Direct Cash Payments ({{ payments.length }})
          </button>
        </div>

        <!-- Tab Content -->
        <div v-show="currentTab === 'sales'" class="bg-white rounded-xl shadow-md p-6 border border-gray-150">
          <div class="overflow-x-auto">
            <DataTable :data="sales" :columns="saleColumns" id="private-sales-table">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Sale Date</th>
                  <th scope="col">Payment Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
            </DataTable>
          </div>
        </div>

        <div v-show="currentTab === 'purchases'" class="bg-white rounded-xl shadow-md p-6 border border-gray-150">
          <div class="overflow-x-auto">
            <DataTable :data="purchases" :columns="purchaseColumns" id="private-purchases-table">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Supplier</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Purchase Date</th>
                  <th scope="col">Payment Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
            </DataTable>
          </div>
        </div>

        <div v-show="currentTab === 'payments'" class="bg-white rounded-xl shadow-md p-6 border border-gray-150">
          <div class="flex justify-between items-center mb-6">
            <h4 class="text-xl font-bold text-gray-800">Private Payment Logs</h4>
            <button 
              @click="showPaymentModal = true"
              class="bg-[#2e2c92] hover:bg-[#1f1d6b] text-white px-4 py-2 rounded-lg font-medium shadow transition-colors flex items-center gap-2"
            >
              <i class="fa fa-plus"></i>
              <span>Record Cash Payment</span>
            </button>
          </div>

          <div class="overflow-x-auto">
            <DataTable :data="payments" :columns="paymentColumns" id="private-payments-table">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Customer</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Amount</th>
                  <th scope="col">Payment Date</th>
                  <th scope="col">Payment Method</th>
                  <th scope="col">Note</th>
                </tr>
              </thead>
            </DataTable>
          </div>
        </div>
      </div>

      <!-- Record Cash Payment Modal -->
      <div 
        v-if="showPaymentModal" 
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
      >
        <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden border border-gray-100 animate-fade-in">
          <div class="bg-[#2e2c92] text-white p-6 flex items-center justify-between">
            <h3 class="text-lg font-bold">Record Private Cash Payment</h3>
            <button @click="showPaymentModal = false" class="text-white hover:text-gray-250 transition-colors">
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>
          
          <form @submit.prevent="submitPayment" class="p-6 space-y-4">
            <div>
              <label for="customer" class="block text-sm font-semibold text-gray-700 mb-1">Customer</label>
              <select 
                id="customer" 
                v-model="paymentForm.customer_id" 
                class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-[#2e2c92]"
                required
              >
                <option value="">Select Customer</option>
                <option 
                  v-for="cust in customers" 
                  :key="cust.id" 
                  :value="cust.id"
                >
                  {{ cust.name }}
                </option>
              </select>
            </div>

            <div>
              <label for="amount" class="block text-sm font-semibold text-gray-700 mb-1">Amount (₹)</label>
              <input 
                type="number" 
                id="amount" 
                v-model="paymentForm.amount" 
                step="0.01" 
                min="0.01"
                placeholder="Enter amount paid"
                class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-[#2e2c92]" 
                required
              />
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label for="date" class="block text-sm font-semibold text-gray-700 mb-1">Payment Date</label>
                <input 
                  type="date" 
                  id="date" 
                  v-model="paymentForm.payment_date" 
                  class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-[#2e2c92]" 
                  required
                />
              </div>

              <div>
                <label for="method" class="block text-sm font-semibold text-gray-700 mb-1">Payment Method</label>
                <select 
                  id="method" 
                  v-model="paymentForm.payment_method" 
                  class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-[#2e2c92]"
                  required
                >
                  <option value="Cash">Cash</option>
                  <option value="Bank">Bank Transfer</option>
                  <option value="Online">Online/UPI</option>
                  <option value="Cheque">Cheque</option>
                </select>
              </div>
            </div>

            <div>
              <label for="note" class="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
              <textarea 
                id="note" 
                v-model="paymentForm.note" 
                rows="3" 
                placeholder="Any memo or internal reference details"
                class="w-full border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-indigo-200 focus:border-[#2e2c92]"
              ></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
              <button 
                type="button" 
                @click="showPaymentModal = false" 
                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              <button 
                type="submit" 
                class="px-5 py-2 bg-[#2e2c92] hover:bg-[#1f1d6b] text-white font-medium rounded-lg shadow transition-colors"
              >
                Record Payment
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.25s ease-out forwards;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.95);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
