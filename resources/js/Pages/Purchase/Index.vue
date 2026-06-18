<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import { ref } from 'vue';
import VerifyInvoiceModal from './VerifyInvoiceModal.vue';

const props = defineProps({
    purchases: {
        type: Array
    },
    suppliers: {
        type: Array,
        default: () => []
    },
    products: {
        type: Array,
        default: () => []
    }
});

const ocrFileInput = ref(null);
const showVerifyModal = ref(false);
const scannedData = ref(null);

function triggerOcrUpload() {
    ocrFileInput.value.click();
}

async function handleOcrUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    Swal.fire({
        title: 'Reading Invoice...',
        text: 'Extracting data using local OCR, please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        if (!window.Tesseract) {
            throw new Error('Tesseract library not loaded properly. Please wait a moment and try again.');
        }
        const Tesseract = window.Tesseract;

        let ocrTarget = file;

        // If it's a PDF, we must convert the first page to an image canvas first!
        if (file.type === 'application/pdf') {
            if (!window.pdfjsLib) {
                throw new Error('PDF.js library not loaded properly. Please wait a moment and try again.');
            }
            const pdfjsLib = window.pdfjsLib;
            
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const page = await pdf.getPage(1); // Read first page
            
            // Higher scale = better OCR resolution
            const viewport = page.getViewport({ scale: 2.0 });
            const canvas = document.createElement('canvas');
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            
            await page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: viewport
            }).promise;
            
            ocrTarget = canvas;
        }

        const worker = await Tesseract.createWorker('eng');
        const ret = await worker.recognize(ocrTarget);
        const text = ret.data.text;
        await worker.terminate();

        const data = { rawText: text };

        // Parse Invoice Number
        const invMatch = text.match(/(?:invoice no|bill no|receipt no|invoice #|bill #|invoice)[\s\.:\-]*([a-zA-Z0-9\-]+)/i);
        if (invMatch) {
            data.invoice_no = invMatch[1];
        }

        // Parse Date - look for explicit "Date:" first, otherwise grab the first date-like string
        let dateMatch = text.match(/(?:date)[\s\.:\-]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i);
        if (!dateMatch) {
            // Fallback: match any DD/MM/YYYY or DD-MM-YYYY pattern
            dateMatch = text.match(/(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/);
        }
        
        if (dateMatch) {
            let dString = dateMatch[1].replace(/\./g, '-');
            let parts = dString.split(/[\/\-]/);
            if (parts.length === 3) {
                // Determine if it's DD/MM or MM/DD based on values
                let val1 = parseInt(parts[0], 10);
                let val2 = parseInt(parts[1], 10);
                let day, month;
                
                if (val2 > 12) {
                    // Must be MM/DD/YYYY
                    month = parts[0].padStart(2, '0');
                    day = parts[1].padStart(2, '0');
                } else {
                    // Assume DD/MM/YYYY (standard Indian format)
                    day = parts[0].padStart(2, '0');
                    month = parts[1].padStart(2, '0');
                }
                
                let year = parts[2].length === 2 ? '20' + parts[2] : parts[2];
                data.purchase_date = `${year}-${month}-${day}`;
            }
        }

        // Smart Matching Logic
        let matchedSupplierId = null;
        let matchedItems = [];
        let grandTotal = 0;

        const lowerText = text.toLowerCase();
        const matchedSupplier = props.suppliers.find(s => s.name && lowerText.includes(s.name.toLowerCase()));
        if (matchedSupplier) {
            matchedSupplierId = matchedSupplier.id;
        }

        const lines = text.split('\n');
        for (const line of lines) {
            const lowerLine = line.toLowerCase();
            const matchedProduct = props.products.find(p => p.name && lowerLine.includes(p.name.toLowerCase()));
            
            if (matchedProduct) {
                const lineWithoutName = lowerLine.replace(matchedProduct.name.toLowerCase(), '');
                const numbers = lineWithoutName.match(/[\d,\.]+/g) || [];
                const cleanNumbers = numbers.map(n => {
                    // Fix: OCR often misreads '.' as ',' (e.g. 110.00 becomes 110,00)
                    if (n.includes(',') && !n.includes('.') && /,\d{2}$/.test(n)) {
                        n = n.replace(/,(?=\d{2}$)/, '.');
                    }
                    const cleaned = n.replace(/[^\d.]/g, '');
                    return parseFloat(cleaned);
                }).filter(n => !isNaN(n));

                let qty = 1;
                let price = matchedProduct.purchase_price || matchedProduct.price || 0;

                let found = false;
                
                // 1. Try to find A * B = C (Qty * Price = Total)
                for (let i = 0; i < cleanNumbers.length - 1; i++) {
                    for (let j = i + 1; j < cleanNumbers.length; j++) {
                        let a = parseFloat(cleanNumbers[i]);
                        let b = parseFloat(cleanNumbers[j]);
                        let cCandidates = cleanNumbers.slice(j + 1).map(parseFloat);
                        
                        for (let c of cCandidates) {
                            if (Math.abs(a * b - c) < 1.0) {
                                qty = Math.min(a, b);
                                price = Math.max(a, b);
                                found = true;
                                break;
                            }
                        }
                        if (found) break;
                    }
                    if (found) break;
                }

                // 2. If Qty was missed by OCR, try to match known price and total
                if (!found && price > 0) {
                    const exactPrice = cleanNumbers.map(parseFloat).find(n => Math.abs(n - price) < 1.0);
                    if (exactPrice) {
                        const total = cleanNumbers.map(parseFloat).find(n => n > exactPrice && Math.abs(n % exactPrice) < 1.0);
                        if (total) {
                            qty = Math.round(total / exactPrice);
                            found = true;
                        }
                    }
                }

                // 3. Fallback to basic guessing if math fails
                if (!found && cleanNumbers.length >= 2) {
                    let num1 = parseFloat(cleanNumbers[0]);
                    let num2 = parseFloat(cleanNumbers[1]);
                    
                    if (Number.isInteger(num1) && num1 < num2 && num2 < 50000) {
                        qty = num1;
                        price = num2;
                    } else if (Number.isInteger(num2) && num2 < num1 && num1 < 50000) {
                        qty = num2;
                        price = num1;
                    } else if (num1 === price || num2 === price) {
                        qty = (num1 === price) ? num2 : num1;
                    } else {
                        qty = 1;
                        price = Math.max(num1, num2);
                    }
                }

                // Fix for OCR missing the decimal point (e.g. reading 120.00 as 12000)
                const dbPrice = parseFloat(matchedProduct.purchase_price || matchedProduct.price || 0);
                if (price > 0 && dbPrice > 0) {
                    if (Math.abs(price / 100 - dbPrice) < 1.0) {
                        price = price / 100;
                    } else if (Math.abs(price / 10 - dbPrice) < 1.0) {
                        price = price / 10;
                    }
                } else if (dbPrice === 0 && price >= 1000 && price % 100 === 0) {
                    // Aggressive fallback for unknown products where OCR completely dropped the dot
                    // e.g., reading 110.00 as 11000
                    price = price / 100;
                }

                let baseAmount = qty * price;
                grandTotal += baseAmount;

                matchedItems.push({
                    product_id: matchedProduct.id,
                    unit_type: matchedProduct.unit_type || "",
                    sgst: parseFloat(matchedProduct.sgst || 0).toFixed(2),
                    cgst: parseFloat(matchedProduct.cgst || 0).toFixed(2),
                    quantity: qty,
                    price: parseFloat(price).toFixed(2),
                    baseAmount: parseFloat(baseAmount).toFixed(2)
                });
            }
        }

        // Remove duplicate products
        const uniqueItems = [];
        const seenProducts = new Set();
        for (const item of matchedItems) {
            if (!seenProducts.has(item.product_id)) {
                seenProducts.add(item.product_id);
                uniqueItems.push(item);
            }
        }

        let transport = 0;
        const transportMatch = text.match(/transport(?: amount| charges| fee|)?\s*[:\-]?\s*(?:rs\.?|inr|₹|)?\s*([\d,\.]+)/i);
        if (transportMatch) {
            transport = parseFloat(transportMatch[1].replace(/,/g, ''));
        }

        if (text && text.trim() !== "") {
            scannedData.value = {
                rawText: text,
                invoice_no: data.invoice_no,
                purchase_date: data.purchase_date,
                transport: transport
            };
            showVerifyModal.value = true;
            Swal.close();
        } else {
            Swal.fire('Error', 'Could not read any text from the invoice.', 'error');
        }

    } catch (err) {
        Swal.fire('Error', 'Failed to read invoice: ' + err.message, 'error');
    }
}

// Column definitions for DataTable
const columns = [
//   { data: 'id', title: 'S No' },
    { 
    data: null,
    title: 'S No',
    render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'supplier_name' },
    { data: 'supplier_phone' },
    { data: 'supplier_email' },
    { data: 'grand_total' },
    { data: 'purchase_Date'},
    { data: 'payment_status'},
    {
        title: 'Actions',
        data: null,
        render: (data, type, row) => {
            return `
            <div class="icon-all-dflex">
              <a href="purchase/${data.id}" class="text-white bg-[#2e2c92] hover:bg-[#201d70] rounded action-btn" style="padding: 2px 8px;" title="View Purchase"><i class="fa fa-eye"></i></a>
              <a  href="purchase/${data.id}/edit" class="btn btn-light action-btn"><i class="fa fa-edit"></i></a>
              <button class="text-white bg-red-600 hover:bg-red-800 px-3 py-1 rounded action-btn delete-btn" data-id="${data.id}"><i class="fa fa-trash"></i></button>
            </div>
            `;
        }
    }
];

// Attach event when component is mounted
onMounted(() => {
  setupDeleteButton()
  
  // Dynamically load Tesseract.js script to avoid Vite warning
  if (!document.getElementById('tesseract-script')) {
      const script = document.createElement('script');
      script.id = 'tesseract-script';
      script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
      document.head.appendChild(script);
  }
  
  // Dynamically load PDF.js script for PDF support
  if (!document.getElementById('pdfjs-script')) {
      const script = document.createElement('script');
      script.id = 'pdfjs-script';
      script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
      document.head.appendChild(script);
  }
});

function setupDeleteButton() {
  document.addEventListener('click', function (event) {
    const button = event.target.closest('.delete-btn');
    if (button) {
      const purchaseId = button.dataset.id;
      deleteSupplier(purchaseId);
    }
  });
}

function deleteSupplier(purchaseId) {
  Swal.fire({
    title: 'Are you sure?',
    text: 'Do you want to delete this purchase?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, delete it!'
  }).then((result) => {
    if (result.isConfirmed) {
      axios.delete(`/purchase/destroy/${purchaseId}`)
        .then(() => {
          Swal.fire('Deleted!', 'Your purchase has been deleted.', 'success');
          location.reload(); // Reload or re-fetch the data if needed
        })
        .catch(() => {
          Swal.fire('Error!', 'Failed to delete the purchase. Please try again.', 'error');
        });
    }
  });
}
</script>

<template>

    <Head title="Purchase">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>

    <AuthenticatedLayout>
        <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold">Purchase</h1>
      <div class="flex items-center gap-4">
        <button @click="triggerOcrUpload"
            class="hover:bg-[#201d70] bg-[#2e2c92] text-white px-4 py-2 rounded-lg font-medium shadow flex items-center">
             <i class="fa fa-camera mr-2"></i> Upload Invoice
        </button>
        <input type="file" ref="ocrFileInput" @change="handleOcrUpload" accept="image/*,application/pdf" class="hidden" />

        <a :href="route('purchase.create')"
            class="hover:bg-[#2e2c92] border border-[#2e2c92] text-black hover:text-white px-4 py-2 rounded-lg font-medium">
             <span>+ Add Purchase</span>
        </a>
      </div>
    </div>
    <div class="overflow-x-auto mt-10">
      <!-- DataTable Component -->
      <DataTable :data="purchases" :columns="columns" id="purchase">
          <thead class="bg-[#2e2c92] text-white main-head-table">
              <tr>
                  <th scope="col">S No</th>
                  <th scope="col">Name</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Email</th>
                  <th scope="col">Total Amount</th>
                  <th scope="col">Purchases Date</th>
                  <th scope="col">Payment Status</th>
                  <th scope="col">Action</th>
              </tr>
          </thead>
          <!-- The table rows would be dynamically inserted here -->
      </DataTable>
    </div>
        </div>

        <VerifyInvoiceModal 
            v-if="showVerifyModal" 
            :ocrData="scannedData"
            :suppliers="suppliers"
            :products="products"
            @close="showVerifyModal = false"
        />

    </AuthenticatedLayout>

</template>