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

const normalizeString = (str) => {
    return str ? str.toLowerCase().replace(/[^a-z0-9]/g, '') : '';
};

const parseOCRNumber = (str) => {
    let clean = str.replace(/\s+/g, '');
    const lastSeparatorMatch = clean.match(/[\.,](\d{1,2})$/);
    if (lastSeparatorMatch) {
        const decimalPart = lastSeparatorMatch[1];
        const separatorIndex = lastSeparatorMatch.index;
        const integerPartRaw = clean.substring(0, separatorIndex);
        const integerPart = integerPartRaw.replace(/\D/g, '');
        return parseFloat(integerPart + '.' + decimalPart);
    } else {
        const integerPart = clean.replace(/\D/g, '');
        return parseFloat(integerPart);
    }
};

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
        title: 'Processing OCR...',
        text: 'Reading invoice content, please wait...',
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

        let text = "";

        // If it's a PDF, we must first try direct text extraction, then fall back to OCR if empty
        if (file.type === 'application/pdf') {
            if (!window.pdfjsLib) {
                throw new Error('PDF.js library not loaded properly. Please wait a moment and try again.');
            }
            const pdfjsLib = window.pdfjsLib;
            
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
            
            const arrayBuffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
            const page = await pdf.getPage(1); // Read first page
            
            // Try direct digital text extraction
            const textContent = await page.getTextContent();
            if (textContent && textContent.items && textContent.items.length > 0) {
                text = textContent.items.map(item => item.str).join('\n');
            }
            
            // Fall back to OCR if digital text is empty or too short
            if (!text || text.trim().length < 50) {
                // Higher scale = better OCR resolution
                const viewport = page.getViewport({ scale: 2.0 });
                const canvas = document.createElement('canvas');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                await page.render({
                    canvasContext: canvas.getContext('2d'),
                    viewport: viewport
                }).promise;
                
                const worker = await Tesseract.createWorker('eng');
                const ret = await worker.recognize(canvas);
                text = ret.data.text;
                await worker.terminate();
            }
        } else {
            // It's an image, run Tesseract OCR
            const worker = await Tesseract.createWorker('eng');
            const ret = await worker.recognize(file);
            text = ret.data.text;
            await worker.terminate();
        }

        const data = { rawText: text };

        // Parse Invoice Number
        const invMatch = text.match(/(?:invoice\s*no|bill\s*no|receipt\s*no|invoice\s*#|bill\s*#|invoice\s*number|bill\s*number|invoice\s*[:\-]|bill\s*[:\-])[\s\.:\-]*([a-zA-Z0-9\-\/_]+)/i);
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

        // Match items globally (pairing products and math tuples in order of appearance)
        const matchedProducts = [];
        const mathTuples = [];
        const lines = text.split('\n');

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const normalizedLine = normalizeString(line);
            if (!normalizedLine) continue;

            // 1. Check for product match
            let matchedProduct = null;
            let maxLen = 0;
            for (const p of props.products) {
                if (!p.name) continue;
                const normalizedName = normalizeString(p.name);
                if (normalizedName && normalizedLine.includes(normalizedName)) {
                    if (normalizedName.length > maxLen) {
                        maxLen = normalizedName.length;
                        matchedProduct = p;
                    }
                }
            }
            if (matchedProduct) {
                matchedProducts.push(matchedProduct);
            }

            // 2. Check for math tuple match on this line
            let lineForNumbers = line.toLowerCase();
            if (matchedProduct) {
                lineForNumbers = lineForNumbers.replace(matchedProduct.name.toLowerCase(), '');
            }
            const numbers = lineForNumbers.match(/[\d,\.]+/g) || [];
            const cleanNumbers = numbers.map(parseOCRNumber).filter(n => !isNaN(n));
            
            let tuple = null;
            for (let iNum = 0; iNum < cleanNumbers.length - 1; iNum++) {
                for (let jNum = iNum + 1; jNum < cleanNumbers.length; jNum++) {
                    let a = cleanNumbers[iNum];
                    let b = cleanNumbers[jNum];
                    let cCandidates = cleanNumbers.slice(jNum + 1);
                    for (let c of cCandidates) {
                        // Avoid trivial matches where both a and b are 1 or small
                        if (Math.abs(a * b - c) < 1.0 && a > 0 && b > 0 && (a > 1.01 || b > 1.01)) {
                            tuple = { qty: Math.min(a, b), price: Math.max(a, b) };
                            break;
                        }
                    }
                    if (tuple) break;
                }
                if (tuple) break;
            }
            if (tuple) {
                mathTuples.push(tuple);
            }
        }

        // If we did not find enough math tuples line-by-line, fall back to global math scanning
        if (mathTuples.length < matchedProducts.length) {
            mathTuples.length = 0; // Clear to avoid partial mixups
            const allNumbersRaw = text.match(/[\d,\.]+/g) || [];
            const allNumbers = allNumbersRaw.map(parseOCRNumber).filter(n => !isNaN(n));
            
            const candidates = [];
            for (let i = 0; i < allNumbers.length; i++) {
                for (let j = 0; j < allNumbers.length; j++) {
                    if (i === j) continue;
                    for (let k = 0; k < allNumbers.length; k++) {
                        if (i === k || j === k) continue;
                        
                        const a = allNumbers[i];
                        const b = allNumbers[j];
                        const c = allNumbers[k];
                        
                        if (Math.abs(a * b - c) < 1.0 && a > 0 && b > 0) {
                            const qty = Math.min(a, b);
                            const price = Math.max(a, b);
                            
                            // Filter out unrealistic quantities/prices (e.g. phone numbers, zip codes, GSTIN parts)
                            if (qty < 10000 && price < 500000) {
                                const isTrivial = (a <= 1.01 || b <= 1.01);
                                const spread = Math.max(i, j, k) - Math.min(i, j, k);
                                candidates.push({
                                    qty,
                                    price,
                                    isTrivial,
                                    spread,
                                    indices: [i, j, k]
                                });
                            }
                        }
                    }
                }
            }

            // Sort candidates: non-trivial first, then by index spread (consecutive / closer numbers first)
            candidates.sort((x, y) => {
                if (x.isTrivial !== y.isTrivial) {
                    return x.isTrivial ? 1 : -1;
                }
                return x.spread - y.spread;
            });

            const usedIndices = new Set();
            for (const cand of candidates) {
                const hasUsedIndex = cand.indices.some(idx => usedIndices.has(idx));
                if (!hasUsedIndex) {
                    mathTuples.push({ qty: cand.qty, price: cand.price });
                    cand.indices.forEach(idx => usedIndices.add(idx));
                }
            }
        }

        // Pair matched products with math tuples by order of index
        for (let i = 0; i < matchedProducts.length; i++) {
            const p = matchedProducts[i];
            const t = mathTuples[i] || { qty: 1, price: p.purchase_price || p.price || 0 };
            
            let finalPrice = t.price;
            let finalQty = t.qty;
            const dbPrice = parseFloat(p.purchase_price || p.price || 0);

            // If we have a non-zero database price, guide the selection of qty and price
            if (dbPrice > 0 && t.price > 0 && t.qty > 0) {
                const ratioPrice = Math.max(t.price / dbPrice, dbPrice / t.price);
                const ratioQty = Math.max(t.qty / dbPrice, dbPrice / t.qty);
                if (ratioQty < ratioPrice && ratioQty < 2.0) {
                    finalPrice = t.qty;
                    finalQty = t.price;
                }
            }
            
            // Fix for OCR missing the decimal point (e.g. reading 120.00 as 12000)
            if (finalPrice > 0 && dbPrice > 0) {
                if (Math.abs(finalPrice / 100 - dbPrice) < 1.0) {
                    finalPrice = finalPrice / 100;
                } else if (Math.abs(finalPrice / 10 - dbPrice) < 1.0) {
                    finalPrice = finalPrice / 10;
                }
            }

            let baseAmount = finalQty * finalPrice;
            grandTotal += baseAmount;

            matchedItems.push({
                product_id: p.id,
                unit_type: p.unit_type || "",
                sgst: parseFloat(p.sgst || 0).toFixed(2),
                cgst: parseFloat(p.cgst || 0).toFixed(2),
                quantity: finalQty,
                price: parseFloat(finalPrice).toFixed(2),
                baseAmount: parseFloat(baseAmount).toFixed(2)
            });
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
    { data: 'invoice_no', title: 'Invoice No' },
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
                  <th scope="col">Invoice No</th>
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