<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

defineProps({
  products: {
        type: Array
    }
});

// Import modal state
const showImportModal = ref(false);
const selectedFile = ref(null);
const isImporting = ref(false);
const importErrors = ref([]);
const fileInput = ref(null);

const openImportModal = () => {
  showImportModal.value = true;
  importErrors.value = [];
  selectedFile.value = null;
};

const closeImportModal = () => {
  if (isImporting.value) return;
  showImportModal.value = false;
  selectedFile.value = null;
  importErrors.value = [];
};

const handleFileChange = (e) => {
  const files = e.target.files;
  if (files && files.length > 0) {
    selectedFile.value = files[0];
  }
};

const clearSelectedFile = () => {
  selectedFile.value = null;
  if (fileInput.value) {
    fileInput.value.value = '';
  }
};

const downloadSampleCsv = () => {
  const headers = ['name', 'category_name', 'unit_type', 'hsn_code', 'sale_price', 'description'];
  const sampleRow = ['Sample Product', 'Electronics', 'Pcs', '8517', '1500.00', 'A premium quality sample product description.'];

  const csvContent = "data:text/csv;charset=utf-8,"
    + [headers.join(','), sampleRow.join(',')].join('\n');

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "products_import_template.csv");
  document.body.appendChild(link);

  link.click();
  document.body.removeChild(link);
};

const submitImport = async () => {
  if (!selectedFile.value) return;

  isImporting.value = true;
  importErrors.value = [];

  const formData = new FormData();
  formData.append('file', selectedFile.value);

  try {
    const response = await axios.post(route('product.import'), formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    isImporting.value = false;
    showImportModal.value = false;

    if (response.data.warnings && response.data.warnings.length > 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Import Completed with Warnings',
        html: `<strong>${response.data.imported_count}</strong> products imported successfully, but some rows had warnings:<br><div style="max-height: 150px; overflow-y: auto; text-align: left; font-size: 12px; color: #d32f2f; margin-top: 10px; padding: 8px; border: 1px solid #ffcdd2; background: #ffebee; border-radius: 6px;">${response.data.warnings.join('<br>')}</div>`,
        confirmButtonColor: '#2e2c92'
      }).then(() => {
        location.reload();
      });
    } else {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: response.data.message || 'Products imported successfully!',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        location.reload();
      });
    }
  } catch (error) {
    isImporting.value = false;
    const errorMsg = error.response?.data?.message || 'Something went wrong during the import process.';
    const validationErrors = error.response?.data?.errors || [];

    if (validationErrors.length > 0) {
      importErrors.value = validationErrors;
      Swal.fire({
        icon: 'error',
        title: 'Import Failed',
        text: 'Some rows in the CSV file have invalid data.',
        confirmButtonColor: '#d33'
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: errorMsg,
        confirmButtonColor: '#d33'
      });
    }
  }
};

const selectedIds = ref([]);

const clearSelection = () => {
  selectedIds.value = [];
  const checkboxes = document.querySelectorAll('.row-checkbox, #select-all-rows');
  checkboxes.forEach(cb => { cb.checked = false; });
};

const setupBulkDeleteListeners = () => {
  document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'select-all-rows') {
      const checkboxes = document.querySelectorAll('.row-checkbox');
      checkboxes.forEach(cb => {
        cb.checked = event.target.checked;
        const id = parseInt(cb.dataset.id);
        if (event.target.checked) {
          if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
        } else {
          selectedIds.value = selectedIds.value.filter(x => x !== id);
        }
      });
    } else if (event.target && event.target.classList.contains('row-checkbox')) {
      const id = parseInt(event.target.dataset.id);
      if (event.target.checked) {
        if (!selectedIds.value.includes(id)) selectedIds.value.push(id);
      } else {
        selectedIds.value = selectedIds.value.filter(x => x !== id);
      }
      const allCb = document.getElementById('select-all-rows');
      if (allCb) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        allCb.checked = checkboxes.length > 0 && Array.from(checkboxes).every(cb => cb.checked);
      }
    }
  });
};

const bulkDelete = () => {
  if (selectedIds.value.length === 0) return;

  Swal.fire({
    title: 'Are you sure?',
    text: `Do you want to delete ${selectedIds.value.length} selected products?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete them!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.post(route('bulk-delete'), {
        resource: 'product',
        ids: selectedIds.value
      })
      .then((response) => {
        const { succeeded, failed } = response.data;
        const totalFailed = Object.keys(failed).length;

        if (totalFailed > 0) {
          let failMessages = '';
          Object.entries(failed).forEach(([id, err]) => {
            failMessages += `Product #${id}: ${err}<br>`;
          });
          Swal.fire({
            icon: 'warning',
            title: 'Bulk Delete Complete with Errors',
            html: `Deleted ${succeeded.length} products.<br>${totalFailed} failed:<br><div style="text-align: left; margin-top: 10px; padding: 10px; background: #fee2e2; border-radius: 6px; font-size: 12px; color: #b91c1c; max-height: 150px; overflow-y: auto;">${failMessages}</div>`,
            confirmButtonColor: '#2e2c92'
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire('Deleted!', 'Selected products deleted successfully.', 'success').then(() => {
            location.reload();
          });
        }
      })
      .catch((error) => {
        Swal.fire('Error!', error.response?.data?.message || 'Failed to delete selected items.', 'error');
      });
    }
  });
};

// DataTables configuration options
const dtOptions = {
  lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
  pageLength: 10,
  order: [[3, 'asc']], // Order by Name by default
  drawCallback: function() {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => {
      const id = parseInt(cb.dataset.id);
      cb.checked = selectedIds.value.includes(id);
    });
    const allCb = document.getElementById('select-all-rows');
    if (allCb) {
      const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
      allCb.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
    }
  }
};

// Column definitions for DataTable
const columns = [
    {
      data: null,
      title: '<input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">',
      orderable: false,
      searchable: false,
      width: '40px',
      render: (data) => {
        const isChecked = selectedIds.value.includes(data.id) ? 'checked' : '';
        return `<input type="checkbox" class="row-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" data-id="${data.id}" ${isChecked}>`;
      }
    },
    {
      data: null,
      title: 'S No',
      render: (data, type, row, meta) => meta.settings._iDisplayStart + meta.row + 1,
    },
    { data: 'name', title: 'Name' },
    { data: 'sku', title: 'Sku' },
    { data: 'categoryName', title: 'Category Name' },
    { 
      data: 'price', 
      title: 'Sale Price',
      render: (data) => data ? `₹${parseFloat(data).toFixed(2)}` : '₹0.00'
    },
    { data: 'stockQuantity', title: 'Stock Quantity' },
    { data: 'unit_type', title: 'Unit Type' },
    {
        title: 'Actions',
        data: null,
        orderable: false,
        searchable: false,
        sortable: false,
        render: (data, type, row) => {
            return `
            <div class="flex gap-2 justify-center items-center">
              <a  href="product/${data.id}/edit" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 4px 8px;"><i class="fa fa-edit"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 rounded action-btn delete-btn px-3 py-1" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        }
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton();
  setupBulkDeleteListeners();
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const productId = button.dataset.id;
      deleteProduct(productId);
    }
  });
}

function deleteProduct(productId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this product?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/product/destroy/${productId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your product has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch((error) => {
          const errMsg = error.response?.data?.message || 'Failed to delete the product. Please try again.';
          Swal.fire('Error!', errMsg, 'error');
        });
    }
  });
}
</script>

<template>

    <Head title="Product">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-3xl font-bold">Products</h1>
          <div class="flex items-center gap-4">
            <button @click="openImportModal" class="bg-[#2e2c92] border border-[#2e2c92] hover:bg-[#201e6e] text-white px-4 py-2 rounded-lg font-medium flex items-center gap-2 cursor-pointer transition-colors shadow-sm">
                <i class="fa fa-file-excel-o"></i>
                <span>Import Products</span>
            </button>
            <a :href="route('product.Create')" class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium transition-colors shadow-sm">
                <span>+ Add Product</span>
            </a>
          </div>
        </div>
        <div class="overflow-x-auto mt-10">
          <!-- DataTable Component -->
          <DataTable :data="products" :columns="columns" :options="dtOptions" id="product">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
                      <th scope="col" style="width: 40px;"><input type="checkbox" id="select-all-rows" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"></th>
                      <th scope="col">S No</th>
                      <th scope="col">Name</th>
                      <th scope="col">Sku</th>
                      <th scope="col">Category Name</th>
                      <th scope="col">Sale Price</th>
                      <th scope="col">Stock Quantity</th>
                      <th scope="col">Unit Type</th>
                      <th scope="col">Action</th>
                  </tr>
              </thead>
              <!-- The table rows would be dynamically inserted here -->
          </DataTable>
        </div>
      </div>

      <!-- Floating Bulk Action Bar -->
      <div v-if="selectedIds.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-white border border-gray-200 rounded-2xl shadow-xl px-6 py-4 flex items-center gap-6">
        <span class="text-sm font-semibold text-gray-700">{{ selectedIds.length }} products selected</span>
        <div class="flex gap-3">
          <button @click="bulkDelete" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold text-sm flex items-center gap-2 transition-colors cursor-pointer shadow-sm">
            <i class="fa fa-trash"></i> Delete Selected
          </button>
          <button @click="clearSelection" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-lg font-semibold text-sm transition-colors cursor-pointer">
            Cancel
          </button>
        </div>
      </div>

      <!-- Import Products Modal -->
      <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm transition-all duration-300">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 transform transition-all duration-300 mx-4">
          <!-- Header -->
          <div class="bg-[#2e2c92] px-6 py-4 flex items-center justify-between text-white shadow-md">
            <h3 class="text-lg font-semibold flex items-center gap-2">
              <i class="fa fa-file-excel-o"></i> Import Products
            </h3>
            <button @click="closeImportModal" class="text-white/80 hover:text-white focus:outline-none transition-colors cursor-pointer">
              <i class="fa fa-times text-xl"></i>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6">
            <p class="text-gray-600 text-sm mb-4">
              Upload a CSV file containing your product catalog.
            </p>

            <!-- Instructions -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4 text-xs text-slate-700 space-y-2">
              <span class="font-bold block text-sm text-slate-800 mb-1">CSV Template Instructions:</span>
              <ul class="list-disc list-inside space-y-1">
                <li><strong>name</strong> (Required) - Product name</li>
                <li><strong>category_name</strong> - Associated category</li>
                <li><strong>unit_type</strong> - Unit type (e.g. Kg, Pcs, Liters)</li>
                <li><strong>hsn_code</strong> - Product HSN code</li>
                <li><strong>sale_price</strong> - Product sale price (e.g. 1500.00)</li>
                <li><strong>description</strong> - Details about the product</li>
              </ul>
            </div>

            <!-- Download link -->
            <div class="mb-5 flex justify-end">
              <button @click="downloadSampleCsv" type="button" class="text-xs text-[#2e2c92] hover:underline flex items-center gap-1 font-semibold cursor-pointer">
                <i class="fa fa-download"></i> Download Sample CSV Template
              </button>
            </div>

            <!-- Upload Area -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
              <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-[#2e2c92] transition-colors relative cursor-pointer group">
                <input type="file" ref="fileInput" @change="handleFileChange" accept=".csv,text/csv" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" />
                <div v-if="!selectedFile" class="space-y-2">
                  <i class="fa fa-cloud-upload text-3xl text-gray-400 group-hover:text-[#2e2c92] transition-colors"></i>
                  <p class="text-sm text-gray-600">Drag and drop your CSV file here, or <span class="text-[#2e2c92] font-semibold">browse</span></p>
                  <p class="text-xs text-gray-400">CSV files only</p>
                </div>
                <div v-else class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg p-3">
                  <div class="flex items-center gap-2 overflow-hidden">
                    <i class="fa fa-file-text-o text-blue-500 text-lg flex-shrink-0"></i>
                    <span class="text-sm font-medium text-blue-800 truncate">{{ selectedFile.name }}</span>
                  </div>
                  <button @click.stop="clearSelectedFile" type="button" class="text-red-500 hover:text-red-700 ml-2 cursor-pointer">
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Validation Warnings -->
            <div v-if="importErrors.length > 0" class="mt-4 max-h-32 overflow-y-auto bg-red-50 border border-red-200 text-red-700 text-xs p-3 rounded-lg space-y-1">
              <p class="font-bold">Import Warnings/Errors:</p>
              <ul class="list-disc list-inside">
                <li v-for="(err, idx) in importErrors" :key="idx">{{ err }}</li>
              </ul>
            </div>
          </div>

          <!-- Footer -->
          <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3 border-t border-gray-100">
            <button @click="closeImportModal" type="button" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 font-medium transition-colors cursor-pointer">
              Cancel
            </button>
            <button @click="submitImport" type="button" :disabled="!selectedFile || isImporting" class="px-5 py-2 bg-[#2e2c92] hover:bg-[#201e6e] text-white rounded-lg text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors cursor-pointer shadow-sm">
              <i v-if="isImporting" class="fa fa-spinner fa-spin"></i>
              <span>{{ isImporting ? 'Importing...' : 'Upload & Import' }}</span>
            </button>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
</template>
