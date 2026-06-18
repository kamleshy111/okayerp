<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

defineProps({
  sales: {
        type: Array
    }
})

// Refs for scanning feature
const scanInput = ref(null);
const isScanning = ref(false);
const showPreviewModal = ref(false);
const scanResult = ref(null);
const availableCustomers = ref([]);
const availableProducts = ref([]);
const isSaving = ref(false);

// Column definitions for DataTable
const columns = [
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
              <a href="sale/${data.id}" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 2px 8px;" title="View Sale"><i class="fa fa-eye"></i></a>
              <a  href="sale/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
              <a href="sale/${data.id}/download-pdf" class="btn btn-primary action-btn"><i class="fa fa-file-pdf-o"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        }
    }
];

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
          location.reload();
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the sale. Please try again.', 'error');
        });
    }
  });
}

// Scanning functions
const triggerScan = () => {
    scanInput.value.click();
};

const compressImage = (file) => {
    return new Promise((resolve) => {
        if (!file.type.startsWith('image/')) {
            resolve(file);
            return;
        }

        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const MAX_WIDTH = 1200;
                let width = img.width;
                let height = img.height;

                if (width > MAX_WIDTH) {
                    height = Math.round((height * MAX_WIDTH) / width);
                    width = MAX_WIDTH;
                }

                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob((blob) => {
                    if (blob) {
                        const compressedFile = new File([blob], file.name, {
                            type: 'image/jpeg',
                            lastModified: Date.now(),
                        });
                        resolve(compressedFile);
                    } else {
                        resolve(file);
                    }
                }, 'image/jpeg', 0.75);
            };
        };
    });
};

const handleFileChange = async (event) => {
    let file = event.target.files[0];
    if (!file) return;

    isScanning.value = true;
    Swal.fire({
        title: 'Processing File...',
        text: 'AI is optimizing the image for faster upload. Please wait.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        file = await compressImage(file);

        Swal.fire({
            title: 'Scanning Invoice...',
            text: 'The invoice for your purchase is being read. Please wait.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const formData = new FormData();
        formData.append('file', file);

        const response = await axios.post('/sale/scan', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        Swal.close();
        if (response.data.success) {
            scanResult.value = response.data.invoice;
            availableCustomers.value = response.data.customers || [];
            availableProducts.value = response.data.products || [];

            if (response.data.mocked) {
                toast.warning("GEMINI_API_KEY is not set. Using dummy data for preview.", { timeout: 8000 });
            } else {
                toast.success("Invoice scanned successfully!");
            }

            showPreviewModal.value = true;
        } else {
            Swal.fire('Scan Failed', response.data.message || 'Unable to scan the invoice.', 'error');
        }
    } catch (error) {
        Swal.close();
        const errMsg = error.response?.data?.message || error.message || 'An error occurred during scan.';
        Swal.fire('Error', errMsg, 'error');
    } finally {
        isScanning.value = false;
        event.target.value = '';
    }
};

const handleProductMatchChange = (item) => {
    if (item.product_id) {
        const selected = availableProducts.value.find(p => p.id === item.product_id);
        if (selected) {
            item.unit_type = selected.unit_type;
            item.cgst = parseFloat(selected.cgst) || 0;
            item.sgst = parseFloat(selected.sgst) || 0;
            item.price = parseFloat(selected.price) || item.price;
        }
    }
};

const removeScannedItem = (index) => {
    if (scanResult.value && scanResult.value.items) {
        scanResult.value.items.splice(index, 1);
    }
};

// Preview computed properties
const previewTotalAmount = computed(() => {
    if (!scanResult.value || !scanResult.value.items) return 0;
    return scanResult.value.items.reduce((sum, item) => {
        return sum + (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0);
    }, 0);
});

const previewTotalGST = computed(() => {
    if (!scanResult.value || !scanResult.value.items) return 0;
    return scanResult.value.items.reduce((sum, item) => {
        const base = (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0);
        const cgst = parseFloat(item.cgst) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        return sum + (base * (cgst + sgst) / 100);
    }, 0);
});

const previewGrandTotal = computed(() => {
    return previewTotalAmount.value + previewTotalGST.value;
});

const modalPaymentFeedback = computed(() => {
    if (!scanResult.value) return { status: 'Unpaid', badgeClass: 'bg-red-100 text-red-800', text: '' };
    const paid = parseFloat(scanResult.value.paid) || 0;
    const grand = parseFloat(previewGrandTotal.value) || 0;

    if (paid === 0) {
        return {
            status: 'Unpaid',
            badgeClass: 'bg-red-100 text-red-800',
            text: `Due: ₹${grand.toFixed(2)}`
        };
    } else if (paid > 0 && paid < grand) {
        const remaining = grand - paid;
        return {
            status: 'Partial',
            badgeClass: 'bg-orange-100 text-orange-800',
            text: `Due: ₹${remaining.toFixed(2)}`
        };
    } else if (paid === grand) {
        return {
            status: 'Paid',
            badgeClass: 'bg-green-100 text-green-800',
            text: 'Fully Paid'
        };
    } else {
        const advance = paid - grand;
        return {
            status: 'Advance',
            badgeClass: 'bg-blue-100 text-blue-800',
            text: `Advance: ₹${advance.toFixed(2)}`
        };
    }
});

const paymentStatus = computed(() => {
    return modalPaymentFeedback.value.status;
});

const saveScannedSale = async () => {
    isSaving.value = true;
    Swal.fire({
        title: 'Saving Sale...',
        text: 'Adding sale and updating stock...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const payload = {
            customer_id: scanResult.value.customer_id,
            customer_name: scanResult.value.customer_name,
            invoice_no: scanResult.value.invoice_no,
            purchase_date: scanResult.value.purchase_date,
            grand_total: previewGrandTotal.value,
            total_amount: previewTotalAmount.value,
            GstAmount: previewTotalGST.value,
            paid: scanResult.value.paid || 0,
            payment_method: scanResult.value.payment_method || 'Cash',
            payment_status: paymentStatus.value,
            purchase_items: scanResult.value.items.map(item => ({
                product_id: item.product_id,
                product_name: item.product_name,
                quantity: item.quantity,
                price: item.price,
                unit_type: item.unit_type || 'pcs',
                baseAmount: (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0),
                cgst: item.cgst || 0,
                sgst: item.sgst || 0
            }))
        };

        const response = await axios.post('/sale/store-scanned', payload);
        Swal.close();

        await Swal.fire({
            icon: 'success',
            title: 'Success',
            text: response.data.message || 'Sale added successfully!'
        });

        showPreviewModal.value = false;
        location.reload();
    } catch (error) {
        Swal.close();
        const errMsg = error.response?.data?.message || error.message || 'An error occurred while saving.';
        Swal.fire('Error', errMsg, 'error');
    } finally {
        isSaving.value = false;
    }
};
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
            </DataTable>
        </div>
      </div>

      <!-- Verification Preview Modal -->
      <div v-if="showPreviewModal" class="fixed inset-0 bg-gray-900 bg-opacity-60 flex items-center justify-center z-50 p-4">
          <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col p-6 space-y-4">
              <!-- Modal Header -->
              <div class="flex items-center justify-between border-b pb-3">
                  <h3 class="text-2xl font-bold text-[#2e2c92]">Verify Scanned Sale Invoice</h3>
                  <button @click="showPreviewModal = false" class="text-gray-400 hover:text-gray-600 transition text-2xl font-semibold">&times;</button>
              </div>

              <!-- Alert / Info -->

              <!-- Modal Body (Scrollable) -->
              <div class="flex-1 overflow-y-auto pr-1 space-y-4 text-black">
                  <!-- Invoice Metadata Grid -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl">
                      <div>
                          <label class="block text-sm font-semibold text-gray-700">Customer</label>
                          <select v-model="scanResult.customer_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 text-black bg-white focus:ring-2 focus:ring-[#2e2c92] focus:outline-none">
                              <option :value="null">➕ Create New: {{ scanResult.customer_name }}</option>
                              <option v-for="cust in availableCustomers" :key="cust.id" :value="cust.id">
                                  {{ cust.name }}
                              </option>
                          </select>
                          <span v-if="!scanResult.customer_id" class="text-xs text-blue-600 mt-1 block">New customer will be added.</span>
                      </div>

                      <div>
                          <label class="block text-sm font-semibold text-gray-700">Invoice Number</label>
                          <input type="text" v-model="scanResult.invoice_no" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 text-black bg-white focus:ring-2 focus:ring-[#2e2c92] focus:outline-none" />
                      </div>

                      <div>
                          <label class="block text-sm font-semibold text-gray-700">Invoice Date</label>
                          <input type="date" v-model="scanResult.purchase_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 text-black bg-white focus:ring-2 focus:ring-[#2e2c92] focus:outline-none" />
                      </div>
                  </div>

                  <!-- Payment info -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-xl">
                      <div>
                          <label class="block text-sm font-semibold text-gray-700">Amount Received (optional)</label>
                          <input type="number" v-model="scanResult.paid" placeholder="0.00" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 text-black bg-white focus:ring-2 focus:ring-[#2e2c92] focus:outline-none" />
                      </div>

                      <div>
                          <label class="block text-sm font-semibold text-gray-700">Payment Method</label>
                          <select v-model="scanResult.payment_method" class="w-full border border-gray-300 rounded-lg px-3 py-2 mt-1 text-black bg-white focus:ring-2 focus:ring-[#2e2c92] focus:outline-none">
                              <option value="Cash">Cash</option>
                              <option value="Bank Transfer">Bank Transfer</option>
                              <option value="Card">Card</option>
                              <option value="UPI">UPI</option>
                          </select>
                      </div>

                      <div class="flex flex-col justify-end">
                          <label class="block text-sm font-semibold text-gray-700 block mb-2">Payment Status</label>
                          <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-1.5 shadow-sm">
                              <span :class="['px-2 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider', modalPaymentFeedback.badgeClass]">
                                  {{ modalPaymentFeedback.status }}
                              </span>
                              <span class="text-xs text-gray-700 font-semibold">
                                  {{ modalPaymentFeedback.text }}
                              </span>
                          </div>
                      </div>
                  </div>

                  <!-- Items Table -->
                  <div class="border rounded-xl overflow-hidden">
                      <table class="w-full table-auto text-sm text-left">
                          <thead class="bg-gray-100 text-gray-700 font-semibold border-b">
                              <tr>
                                  <th class="px-4 py-2">Item Product</th>
                                  <th class="px-4 py-2 w-20">Qty</th>
                                  <th class="px-4 py-2 w-28">Price (₹)</th>
                                  <th class="px-4 py-2 w-20">Tax %</th>
                                  <th class="px-4 py-2">Net (₹)</th>
                                  <th class="px-4 py-2 w-16">Action</th>
                              </tr>
                          </thead>
                          <tbody>
                              <tr v-for="(item, index) in scanResult.items" :key="index" class="border-b hover:bg-gray-50">
                                  <td class="px-4 py-3">
                                      <select v-model="item.product_id" @change="handleProductMatchChange(item)" class="w-full border border-gray-300 rounded px-2 py-1 text-black bg-white focus:outline-none focus:ring-1 focus:ring-[#2e2c92]">
                                          <option :value="null">➕ Create New: {{ item.product_name }}</option>
                                          <option v-for="p in availableProducts" :key="p.id" :value="p.id">
                                              {{ p.name }}
                                          </option>
                                      </select>
                                      <span v-if="!item.product_id" class="text-[11px] text-blue-600 mt-0.5 block">New product will be added.</span>
                                  </td>
                                  <td class="px-4 py-3">
                                      <input type="number" v-model="item.quantity" class="w-full border border-gray-300 rounded px-2 py-1 text-black bg-white text-center focus:outline-none focus:ring-1 focus:ring-[#2e2c92]" />
                                  </td>
                                  <td class="px-4 py-3">
                                      <input type="number" v-model="item.price" class="w-full border border-gray-300 rounded px-2 py-1 text-black bg-white text-right focus:outline-none focus:ring-1 focus:ring-[#2e2c92]" />
                                  </td>
                                  <td class="px-4 py-3 text-center">
                                      {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }}%
                                  </td>
                                  <td class="px-4 py-3 text-right font-medium">
                                      ₹{{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)).toFixed(2) }}
                                  </td>
                                  <td class="px-4 py-3 text-center">
                                      <button @click="removeScannedItem(index)" class="text-red-500 hover:text-red-700 font-bold"><i class="fa fa-trash"></i></button>
                                  </td>
                              </tr>
                          </tbody>
                      </table>
                  </div>

                  <!-- Summary Section -->
                  <div class="flex justify-end">
                      <div class="w-full md:w-80 bg-gray-50 rounded-xl p-4 space-y-2 text-sm border">
                          <div class="flex justify-between">
                              <span class="text-gray-600">Subtotal Amount:</span>
                              <span class="font-medium">₹{{ previewTotalAmount.toFixed(2) }}</span>
                          </div>
                          <div class="flex justify-between">
                              <span class="text-gray-600">GST Amount:</span>
                              <span class="font-medium">₹{{ previewTotalGST.toFixed(2) }}</span>
                          </div>
                          <div class="flex justify-between border-t pt-2 text-base font-bold text-[#2e2c92]">
                              <span>Grand Total:</span>
                              <span>₹{{ previewGrandTotal.toFixed(2) }}</span>
                          </div>
                      </div>
                  </div>
              </div>

              <!-- Modal Footer -->
              <div class="flex justify-end gap-3 border-t pt-3">
                  <button @click="showPreviewModal = false" class="px-5 py-2 border rounded-lg hover:bg-gray-100 transition text-gray-700">Cancel</button>
                  <button @click="saveScannedSale" :disabled="isSaving" class="px-5 py-2 bg-[#2e2c92] hover:bg-[#201d70] text-white rounded-lg transition font-medium flex items-center gap-2">
                      <span v-if="isSaving">Saving...</span>
                      <span v-else>Confirm & Add</span>
                  </button>
              </div>
          </div>
      </div>
    </AuthenticatedLayout>
</template>
