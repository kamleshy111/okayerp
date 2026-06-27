<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import vSelect from 'vue3-select';
import 'vue3-select/dist/vue3-select.css';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
    ocrData: {
        type: Object,
        required: true
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

const emit = defineEmits(['close', 'save']);

const form = ref({
    supplier_id: "",
    invoice_no: "",
    purchase_date: "",
    paid: 0,
    payment_method: "Cash",
    accepted: 0,
    transport: 0,
    purchase_items: []
});

const supplierData = ref(null);

watch(() => form.value.supplier_id, async (newId) => {
    if (newId) {
        try {
            const response = await axios.get(`/purchase/payment/${newId}`);
            supplierData.value = response.data;
        } catch (error) {
            supplierData.value = null;
        }
    } else {
        supplierData.value = null;
    }
}, { immediate: true });

watch(() => form.value.purchase_items, (newItems) => {
    newItems.forEach(item => {
        const selectedProduct = props.products.find(p => p.id === item.product_id);
        if (selectedProduct) {
            // Only update if the user hasn't manually overridden the tax/unit yet
            item.unit_type = selectedProduct.unit_type;
            item.sgst = selectedProduct.sgst;
            item.cgst = selectedProduct.cgst;
            
            // If price wasn't set by OCR, fill it
            if (!item.price || item.price === 0) {
                item.price = selectedProduct.purchase_price || selectedProduct.price || 0;
            }
        }
    });
}, { deep: true });

// Initialization based on OCR Data
onMounted(() => {
    form.value.invoice_no = props.ocrData.invoice_no || "";
    form.value.purchase_date = props.ocrData.purchase_date || new Date().toISOString().substring(0, 10);
    form.value.transport = props.ocrData.transport || 0;
    
    // Attempt to auto-select supplier
    if (props.ocrData.rawText) {
        const lowerText = props.ocrData.rawText.toLowerCase();
        const matchedSupplier = props.suppliers.find(s => s.name && lowerText.includes(s.name.toLowerCase()));
        if (matchedSupplier) {
            form.value.supplier_id = matchedSupplier.id;
        }

        // Match items
        const matchedItems = [];
        const lines = props.ocrData.rawText.split('\n');
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
                    price = price / 100;
                }

                matchedItems.push({
                    product_id: matchedProduct.id,
                    unit_type: matchedProduct.unit_type || "",
                    sgst: parseFloat(matchedProduct.sgst || 0).toFixed(2),
                    cgst: parseFloat(matchedProduct.cgst || 0).toFixed(2),
                    quantity: qty,
                    price: parseFloat(price).toFixed(2),
                    baseAmount: parseFloat(qty * price).toFixed(2)
                });
            }
        }

        // Deduplicate
        const uniqueItems = [];
        const seenProducts = new Set();
        for (const item of matchedItems) {
            if (!seenProducts.has(item.product_id)) {
                seenProducts.add(item.product_id);
                uniqueItems.push(item);
            }
        }

        if (uniqueItems.length > 0) {
            form.value.purchase_items = uniqueItems;
        } else {
            form.value.purchase_items = [{ product_id: "", quantity: 1, price: 0, sgst: 0, cgst: 0 }];
        }
    } else {
        form.value.purchase_items = [{ product_id: "", quantity: 1, price: 0, sgst: 0, cgst: 0 }];
    }
});

const removeRow = (index) => {
    if (form.value.purchase_items.length > 1) {
        form.value.purchase_items.splice(index, 1);
    }
};

const addRow = () => {
    form.value.purchase_items.push({
        product_id: "", quantity: 1, price: 0, sgst: 0, cgst: 0
    });
};

const totalGST = computed(() => {
    return form.value.purchase_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const gst = sgst + cgst;
        return sum + ((quantity * price) * gst / 100);
    }, 0);
});

const totalAmount = computed(() => {
    return form.value.purchase_items.reduce((sum, item) => {
        return sum + ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0));
    }, 0);
});

const grandTotal = computed(() => {
    return totalAmount.value + totalGST.value + (parseFloat(form.value.transport) || 0);
});

// Automatically pre-fill the Amount Paid with the Grand Total so it's fully paid by default!
watch(grandTotal, (newTotal) => {
    if (newTotal > 0) {
        form.value.paid = newTotal.toFixed(2);
    }
}, { immediate: true });
const paymentStatus = computed(() => {
    const paidNow = parseFloat(form.value.paid) || 0;
    if (paidNow === 0) return 'UNPAID';
    if (paidNow >= grandTotal.value) return 'PAID';
    return 'PARTIAL';
});

const calculatedDue = computed(() => {
    let baseDue = grandTotal.value - (parseFloat(form.value.paid) || 0);
    return baseDue > 0 ? baseDue.toFixed(2) : '0.00';
});
const handleSave = async () => {
    if (!form.value.supplier_id) {
        Swal.fire('Error', 'Please select a supplier.', 'error');
        return;
    }
    if (form.value.purchase_items.length === 0 || !form.value.purchase_items[0].product_id) {
        Swal.fire('Error', 'Please add at least one product.', 'error');
        return;
    }

    Swal.fire({ title: 'Saving...', allowOutsideClick: false });
    Swal.showLoading();

    try {
        const payload = {
            supplier_id: form.value.supplier_id,
            invoice_no: form.value.invoice_no,
            purchase_date: form.value.purchase_date,
            transport: form.value.transport || 0,
            grand_total: grandTotal.value,
            total_amount: totalAmount.value,
            GstAmount: totalGST.value,
            accepted: 1,
            paid: form.value.paid,
            payment_method: form.value.payment_method,
            payment_status: paymentStatus.value,
            purchase_items: form.value.purchase_items
        };

        await axios.post('/purchase/store', payload);
        Swal.fire({
            title: 'Success!',
            text: 'Purchase auto-created directly from Invoice!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            location.reload();
        });
    } catch (err) {
        Swal.fire('Error', 'Failed to save purchase: ' + err.message, 'error');
    }
};

</script>

<template>
    <div class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6" style="z-index: 99999;" @click.self="emit('close')">
        <div class="bg-gray-50 p-6 rounded-xl shadow-2xl w-full max-w-5xl my-auto transform transition-all duration-300 border border-gray-100">
            
            <div class="flex justify-between items-center pb-4 border-b border-gray-200 mb-6">
                <h2 class="text-2xl font-bold text-[#292688]">Verify Scanned Invoice</h2>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition text-2xl leading-none">
                    &times;
                </button>
            </div>

            <!-- Top Row: Supplier, Invoice, Date -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 bg-white p-4 rounded-lg shadow-sm">
                <div>
                    <label class="block text-gray-700 font-medium mb-1 text-sm">Supplier</label>
                    <vSelect
                        v-model="form.supplier_id"
                        :options="suppliers"
                        label="name"
                        :reduce="s => s.id"
                        placeholder="Search or select supplier"
                        class="w-full bg-white"
                    />
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1 text-sm">Invoice Number</label>
                    <input type="text" v-model="form.invoice_no" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1 text-sm">Invoice Date</label>
                    <input type="date" v-model="form.purchase_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
            </div>

            <!-- Middle Row: Payment -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 bg-white p-4 rounded-lg shadow-sm">
                <div>
                    <label class="block text-gray-700 font-medium mb-1 text-sm">Amount Paid (optional)</label>
                    <input type="text" v-model="form.paid" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1 text-sm">Payment Method</label>
                    <select v-model="form.payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="Null">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1 text-sm">Payment Status</label>
                    <div class="flex items-center gap-3 mt-2">
                        <span :class="{'bg-red-100 text-red-600': paymentStatus==='UNPAID', 'bg-green-100 text-green-600': paymentStatus==='PAID', 'bg-yellow-100 text-yellow-600': paymentStatus==='PARTIAL'}" class="px-3 py-1 rounded text-xs font-bold tracking-wide uppercase">
                            {{ paymentStatus }}
                        </span>
                        <span class="text-sm font-semibold text-gray-600">Due: ₹{{ calculatedDue }}</span>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                <table class="w-full table-auto">
                    <thead class="bg-gray-100 text-gray-600 text-sm">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Item Product</th>
                            <th class="px-4 py-3 text-left font-medium w-24">Qty</th>
                            <th class="px-4 py-3 text-left font-medium w-32">Price (₹)</th>
                            <th class="px-4 py-3 text-left font-medium w-20">Tax %</th>
                            <th class="px-4 py-3 text-left font-medium w-32">Net (₹)</th>
                            <th class="px-4 py-3 text-center font-medium w-16">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, index) in form.purchase_items" :key="index" class="border-t border-gray-100">
                            <td class="px-4 py-3">
                                <vSelect
                                    v-model="item.product_id"
                                    :options="products"
                                    label="name"
                                    :reduce="p => p.id"
                                    placeholder="Select product"
                                    class="w-full bg-white"
                                    append-to-body
                                />
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" v-model="item.quantity" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                            </td>
                            <td class="px-4 py-3">
                                <input type="text" v-model="item.price" @blur="item.price = parseFloat(item.price || 0).toFixed(2)" class="w-full px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none" />
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }}%
                            </td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-800">
                                ₹{{ ((parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0)).toFixed(2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button @click="removeRow(index)" class="text-red-500 hover:text-red-700 transition">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" class="px-4 py-3 border-t border-gray-100">
                                <button @click="addRow" class="text-[#292688] hover:text-[#1d1b6a] font-medium text-sm transition flex items-center gap-1">
                                    <i class="fa fa-plus"></i> Add Item
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Totals & Actions -->
            <div class="flex flex-col sm:flex-row justify-end items-end sm:items-center gap-6">
                <div class="bg-gray-100 p-4 rounded-lg w-full sm:w-72 flex flex-col gap-2">
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>Subtotal Amount:</span>
                        <span class="font-bold text-gray-800">₹{{ totalAmount.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-600">
                        <span>GST Amount:</span>
                        <span class="font-bold text-gray-800">₹{{ totalGST.toFixed(2) }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm text-gray-600 border-t border-gray-200 pt-2">
                        <span>Transport Amount:</span>
                        <div class="relative w-28">
                            <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500">₹</span>
                            <input type="text" v-model="form.transport" class="w-full pl-6 pr-2 py-1 text-right border border-gray-300 rounded focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white font-bold text-gray-800" placeholder="0" />
                        </div>
                    </div>
                    <div class="flex justify-between text-base border-t border-gray-200 pt-2 mt-1">
                        <span class="font-bold text-[#292688]">Grand Total:</span>
                        <span class="font-black text-[#292688]">₹{{ grandTotal.toFixed(2) }}</span>
                    </div>
                </div>
                
                <div class="flex gap-3 w-full sm:w-auto">
                    <button @click="emit('close')" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition font-medium w-full sm:w-auto">
                        Cancel
                    </button>
                    <button @click="handleSave" class="px-6 py-2 bg-[#292688] text-white rounded-lg hover:bg-[#1d1b6a] shadow-md transition font-medium w-full sm:w-auto">
                        Confirm & Add
                    </button>
                </div>
            </div>

        </div>
    </div>
</template>
