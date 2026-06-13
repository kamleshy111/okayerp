<script setup>
import { ref, watch, computed, watchEffect  } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

const props = defineProps({
  suppliers: {
    type: Array,
    required: true,
  },
  products: {
    type: Array,
    required: true,
  },
  purchases: {
    type: Object,
    required: true,
  },
  productItems: {
    type: Array,
    required: true,
  },
  allocatedPayment: {
    type: [Number, String],
    default: 0,
  },
});

const suppliers = props.suppliers;
const products = props.products;
const productItems = props.productItems;
const purchases = props.purchases;

const form = ref({
    id: purchases.id,
    supplier_id: purchases.supplier_id,
    invoice_no: purchases.invoice_no || '',
    purchase_date: purchases.purchase_date || '',
    transport: purchases.transport_amount,
    grand_total: "",
    GstAmount: "",
    accepted: purchases.accepted  === 1,
    paid: purchases.paid,
    payment_method: purchases.payment_method,
    purchase_items: productItems.map(item => ({
                    product_id: item.product_id,
                    quantity: item.quantity,
                    price: item.price,
                    unit_type: item.unit_type,
                    sgst: item.sgst,
                    cgst: item.cgst,
    })),
});

const selectedSupplier = ref(null);

watchEffect(() => {
  if (form.value.supplier_id && suppliers.length > 0) {
    selectedSupplier.value = suppliers.find(s => s.id == form.value.supplier_id) || null;

    console.log(selectedSupplier.value.id);
  }
});

const supplierData = ref(null);

watch(
  () => selectedSupplier.value,
  async (supplier) => {
    const newId = supplier?.id;
    if (newId) {
      try {
        const response = await axios.get(`/purchase/payment/${newId}`);
        supplierData.value = response.data;
      } catch (error) {
        console.error('Error fetching supplier data:', error);
        supplierData.value = null;
      }
    } else {
      supplierData.value = null;
    }
  },
  { immediate: true }
);

// Watch for product change in each row to update unit_type
watch(() => form.value.purchase_items, (newSaleItems) => {
  newSaleItems.forEach(item => {
    const selectedProduct = products.find(product => product.id === item.product_id);
    if (selectedProduct) {
      item.unit_type = selectedProduct.unit_type;
      item.sgst = selectedProduct.sgst;
      item.cgst = selectedProduct.cgst;

      const quantity = parseFloat(item.quantity) || 0;
      const price = parseFloat(item.price) || 0;
      const baseAmount = quantity * price;
      item.baseAmount = baseAmount;
    }
  });
}, { deep: true });

const addRow = () => {
    // Add a new row to the purchase_items array
    form.value.purchase_items.push({
        product_id: "",
        unit_type: "",
        quantity: "",
        price: "",
        gst: "",
    });
};

const removeRow = (index) => {
    if (form.value.purchase_items.length > 1) {
        form.value.purchase_items.splice(index, 1);
    } else {
        toast.error("At least one row is required.");
    }
};

const totalGST = computed(() => {
    if (!form.value.accepted) return 0;
    return form.value.purchase_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const sgst = parseFloat(item.sgst) || 0;
        const cgst = parseFloat(item.cgst) || 0;
        const gst = sgst + cgst;
        const baseAmount = quantity * price;
        return sum + (baseAmount * gst / 100);
    }, 0);
});

const totalAmount = computed(() => {
    return form.value.purchase_items.reduce((sum, item) => {
        const quantity = parseFloat(item.quantity) || 0;
        const price = parseFloat(item.price) || 0;
        const baseAmount = quantity * price;
        return sum + baseAmount;
        
    }, 0);
});

const grandTotal = computed(() => {
    const transport = parseFloat(form.value.transport) || 0;
    return totalAmount.value + transport + totalGST.value;
});

const dueAmount = computed(() => {
  const paidValue = (parseFloat(form.value.paid) || 0) + (parseFloat(props.allocatedPayment) || 0);
  const difference = paidValue - grandTotal.value;

  if (difference > 0) {
    return { type: 'advance', amount: difference };
  } else if (difference < 0) {
    return { type: 'due', amount: Math.abs(difference) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const supplierBalanceExcludingCurrentPurchase = computed(() => {
  if (!supplierData.value) return 0;
  
  const currentAdvance = parseFloat(supplierData.value.advance_amount) || 0;
  const currentDue = parseFloat(supplierData.value.due_amount) || 0;
  const currentNet = currentAdvance - currentDue;
  
  const originalPaid = parseFloat(props.purchases.paid) || 0;
  const originalGrandTotal = parseFloat(props.purchases.grand_total) || 0;
  const originalNet = originalPaid - originalGrandTotal;
  
  return currentNet - originalNet;
});

const previousAdvance = computed(() => {
  const bal = supplierBalanceExcludingCurrentPurchase.value;
  return bal > 0 ? bal : 0;
});

const previousDue = computed(() => {
  const bal = supplierBalanceExcludingCurrentPurchase.value;
  return bal < 0 ? Math.abs(bal) : 0;
});

const finalBalance = computed(() => {
  if (!supplierData.value) return null;
  const prevNet = supplierBalanceExcludingCurrentPurchase.value;
  const paidNow = parseFloat(form.value.paid) || 0;
  const currentNet = paidNow - grandTotal.value;
  
  const finalNet = prevNet + currentNet;
  
  if (finalNet > 0) {
    return { type: 'advance', amount: finalNet };
  } else if (finalNet < 0) {
    return { type: 'due', amount: Math.abs(finalNet) };
  } else {
    return { type: 'none', amount: 0 };
  }
});

const advanceApplied = computed(() => {
  const prevAdvance = previousAdvance.value;
  const paidNow = parseFloat(form.value.paid) || 0;
  return Math.min(prevAdvance, Math.max(0, grandTotal.value - paidNow));
});

const dueReduced = computed(() => {
  const prevDue = previousDue.value;
  const paidNow = parseFloat(form.value.paid) || 0;
  return Math.min(prevDue, Math.max(0, paidNow - grandTotal.value));
});

const paymentStatus = computed(() => {
  const paidValue = (parseFloat(form.value.paid) || 0) + (parseFloat(props.allocatedPayment) || 0);
  if (paidValue === 0) return 'Unpaid';
  else if (paidValue < grandTotal.value) return 'Partial';
  else return 'Paid';
});

const showPaymentModal = ref(false);

// Show modal first, then submit all
const openPaymentModal = () => {
    const { supplier_id, purchase_items } = form.value;

    if (!supplier_id) {
        toast.error("Please select a customer.");
        return;
    }

    for (let i = 0; i < purchase_items.length; i++) {
        const item = purchase_items[i];
        const quantity = parseFloat(item.quantity);
        const price = parseFloat(item.price);

        if (!item.product_id) {
            toast.error(`Item ${i + 1}: Product is required.`);
            return;
        }

        if (isNaN(quantity) || quantity <= 0) {
            toast.error(`Item ${i + 1}: Quantity is required.`);
            return;
        }

        if (isNaN(price) || price <= 0) {
            toast.error(`Item ${i + 1}: Price is required.`);
            return;
        }
    }
    showPaymentModal.value = true;
};

// Submit the form data
const submitForm = async () => {
  try {
    const payload = {
      ...form.value,
      grand_total: grandTotal.value,
      total_amount: totalAmount.value,
      GstAmount: totalGST.value,
      payment_status: paymentStatus.value,
    };

    const response = await axios.post(`/purchase/update/${form.value.id}`, payload);
    toast.success(response.data.message);
    showPaymentModal.value = false;

  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

</script>
<template>

    <Head title="Purchase">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('purchase')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Purchase</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Update Purchase</h2>
        <div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Supplier</label>
                    <select   name="supplier_id" v-model="form.supplier_id"
                    class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                    <option value="" disabled>Select Supplier</option>
                        <option v-for="supplier in suppliers" :key="supplier.id"
                                :value="supplier.id"> {{ supplier.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Invoice / Bill Number</label>
                    <input type="text" name="invoice_no" v-model="form.invoice_no"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter invoice/bill number" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Purchase Date</label>
                    <input type="date" name="purchase_date" v-model="form.purchase_date"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-if="selectedSupplier" class="mt-4 p-4 border rounded bg-gray-100 text-black">
                <p><strong>Name:</strong> {{ selectedSupplier.name }}</p>
                <p><strong>Phone:</strong> {{ selectedSupplier.phone }}</p>
                <p><strong>Address:</strong> {{ selectedSupplier.address }}</p>
                </div>
            </div>

            <div>
                <h3 class="text-2xl font-bold mb-4 text-[#292688]">Purchase Items</h3>
            </div>

            <table class="w-full table-auto border border-gray-300 rounded-xl overflow-hidden">
                <thead class="bg-[#292688] text-white">
                    <tr>
                        <th class="px-4 py-2 text-left">Product</th>
                        <th class="px-4 py-2 text-left">GST</th>
                        <th class="px-4 py-2 text-left">Quantity</th>
                        <th class="px-4 py-2 text-left">Unit Type</th>
                        <th class="px-4 py-2 text-left">Price</th>
                        <th class="px-4 py-2 text-left">Net Amount</th>
                        <th class="px-4 py-2 text-left">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in form.purchase_items" :key="index">
                        <td class="border-t px-4 py-3">
                            <select name="product_id" v-model="item.product_id"
                                class="w-full px-3 py-2 bg-white text-black border border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                                <option value="" disabled>Select Product</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }}
                                </option>
                            </select>
                        </td>

                        <td class="border-t px-4 py-3">
                            {{ (parseFloat(item.sgst) || 0) + (parseFloat(item.cgst) || 0) }} %
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="number" name="quantity" v-model="item.quantity" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Qty" />
                        </td>
                        <td class="border-t px-4 py-3">
                            {{ item.unit_type }}
                        </td>
                        <td class="border-t px-4 py-3">
                            <input type="number" name="price" v-model="item.price" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Price" />
                        </td>
                        <td class="border-t px-4 py-3">
                            ₹  {{ (parseFloat(item.quantity) || 0) * (parseFloat(item.price) || 0) }}
                        </td>

                        <td class="border-t px-4 py-2">
                            <button @click="removeRow(index)" type="button"
                                class="bg-red-600 text-white px-6 py-2 rounded-md shadow hover:bg-red-700 transition mr-2">
                                Remove
                            </button>

                            <button v-if="index === form.purchase_items.length - 1" @click="addRow" type="button"
                                class="bg-green-600 text-white px-6 py-2 rounded-md shadow hover:bg-green-700 transition">
                                Add Items
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="flex justify-end mt-5">
                <button @click="openPaymentModal" class="bg-blue-600 text-white px-4 py-2 rounded">
                Submit & Proceed to Payment
                </button>
            </div>
        </div>
    </div>
    <!-- Payment Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-xl w-96">
            <h2 class="text-2xl font-bold mb-4">Payment Details</h2>
            
            <div class="mt-6 space-y-4 border-t pt-4">

                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Transport Amount</label>
                    <input type="number" v-model="form.transport"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition" />
                </div>

                <div class="flex justify-between items-center">
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox" v-model="form.accepted" class="form-checkbox h-5 w-5 text-[#292688]">
                        <span class="text-sm text-gray-700">Apply To GST</span>
                    </label>
                </div>

                <div v-if="form.accepted" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">GST</span>
                    <span class="text-gray-800 font-bold">₹ {{ totalGST.toFixed(2) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Total Net Amount</span>
                    <span class="text-gray-800 font-bold">₹ {{ totalAmount.toFixed(2) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Grand Total</span>
                    <span class="text-black font-bold text-lg">₹ {{ grandTotal.toFixed(2) }}</span>
                </div>

                <!-- Payment Method -->
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Payment Method</label>
                    <select v-model="form.payment_method"
                            class="w-40 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>

                <!-- Paid Amount -->
                <div class="flex justify-between items-center">
                    <label class="text-gray-700 font-medium">Paid Amount</label>
                    <input type="number" v-model="form.paid"
                        class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" min="0" />
                </div>

                <!-- Allocated General Payments -->
                <div v-if="parseFloat(props.allocatedPayment) > 0" class="flex justify-between items-center text-sm text-gray-500">
                    <span>Supplier Payments Applied</span>
                    <span class="font-medium text-[#292688]">₹ {{ props.allocatedPayment }}</span>
                </div>

                <!-- Previous Balances -->
                <div v-if="previousAdvance > 0" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Previous Advance</span>
                    <span class="text-green-600 font-bold">₹ {{ previousAdvance.toFixed(2) }}</span>
                </div>

                <div v-if="previousDue > 0" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Previous Due</span>
                    <span class="text-red-600 font-bold">₹ {{ previousDue.toFixed(2) }}</span>
                </div>

                <!-- Wallet / Due Adjustments -->
                <div v-if="advanceApplied > 0" class="flex justify-between items-center text-sm text-gray-500">
                    <span>Advance Applied</span>
                    <span class="font-medium text-blue-600">- ₹ {{ advanceApplied.toFixed(2) }}</span>
                </div>

                <div v-if="dueReduced > 0" class="flex justify-between items-center text-sm text-gray-500">
                    <span>Applied to Previous Due</span>
                    <span class="font-medium text-green-600">- ₹ {{ dueReduced.toFixed(2) }}</span>
                </div>

                <!-- Final Balance after this payment -->
                <div v-if="finalBalance?.type === 'advance'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Advance</span>
                    <span class="text-green-600 font-bold">₹ {{ finalBalance.amount.toFixed(2) }}</span>
                </div>

                <div v-if="finalBalance?.type === 'due'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Due</span>
                    <span class="text-red-600 font-bold">₹ {{ finalBalance.amount.toFixed(2) }}</span>
                </div> 

                <div v-if="finalBalance?.type === 'none'" class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Final Balance</span>
                    <span class="text-gray-600 font-bold">₹ 0.00 (Clear)</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="text-gray-700 font-semibold">Payment Status</span>
                    <span class="text-blue-600 font-bold">{{ paymentStatus }}</span>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-between">
                    <button @click="submitForm" class="bg-green-600 text-white px-4 py-2 rounded">Final Submit</button>
                    <button @click="showPaymentModal = false" class="bg-gray-300 text-black px-4 py-2 rounded">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    </AuthenticatedLayout>
</template>