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
  const headers = ['name', 'category_name', 'unit_type', 'cgst', 'sgst', 'hsn_code', 'description'];
  const sampleRow = ['Sample Product', 'Electronics', 'Pcs', '9', '9', '8517', 'A premium quality sample product description.'];
  
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

// Column definitions for DataTable
const columns = [
//   { data: 'id', title: 'S No' },
    { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'name' },
    { data: 'sku'},
    { data: 'categoryName' ?? '---'},
    { data: 'stockQuantity'},
    { data: 'unit_type'},
    { data: 'cgst'},
    { data: 'sgst'},
    {
        title: 'Actions',
        data: null,
        orderable: false,
        searchable: false, 
        render: (data, type, row) => {
            return `
            <div class="icon-all-dflex">
              <a  href="product/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
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
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the product. Please try again.', 'error');
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
          <DataTable :data="products" :columns="columns" id="product">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
                      <th scope="col">S No</th>
                      <th scope="col">Name</th>
                      <th scope="col">Sku</th>
                      <th scope="col">Category Name</th>
                      <th scope="col">Stock Quantity</th>
                      <th scope="col">Unit Type</th>
                      <th scope="col">SGST</th>
                      <th scope="col">CGST</th>
                      <th scope="col">Action</th>
                  </tr>
              </thead>
              <!-- The table rows would be dynamically inserted here -->
          </DataTable>
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
                <li><strong>cgst</strong> - CGST % (e.g. 9)</li>
                <li><strong>sgst</strong> - SGST % (e.g. 9)</li>
                <li><strong>hsn_code</strong> - Product HSN code</li>
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