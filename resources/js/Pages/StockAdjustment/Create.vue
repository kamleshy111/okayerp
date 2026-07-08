<script setup>
import { ref, watch, computed, nextTick, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const props = defineProps({
  products: {
    type: Array,
    required: false,
    default: () => [],
  }
});

const products = ref([]);
const productRegistry = ref({});

if (props.products) {
  props.products.forEach(p => {
    productRegistry.value[p.id] = p;
  });
}

const form = ref({
    product_id: "",
    type: "Addition", // Default to adding stock
    quantity: "",
    reason: "",
    remarks: ""
});

// Move focus to the next logical input/select/button
const moveToNextInput = (event) => {
  const container = document.querySelector('.bg-white.p-8');
  if (!container) return;

  const elements = Array.from(container.querySelectorAll(
    'input:not([disabled]), select:not([disabled]), button:not([disabled]), .vs__search'
  )).filter(el => {
    const rect = el.getBoundingClientRect();
    const isVisible = rect.width > 0 && rect.height > 0;
    const isTrashBtn = el.querySelector('.bi-trash') || el.classList.contains('bg-red-600') || el.closest('button')?.classList.contains('bg-red-600') || el.querySelector('.bi-trash-fill') || el.closest('button')?.querySelector('.bi-trash-fill');
    const isAddRowBtn = el.closest('button')?.classList.contains('bg-green-600') || el.classList.contains('bg-green-600');
    return isVisible && !isTrashBtn && !isAddRowBtn;
  });

  const currentIndex = elements.indexOf(event.target);
  if (currentIndex !== -1 && currentIndex < elements.length - 1) {
    event.preventDefault();
    elements[currentIndex + 1].focus();
  }
};

const productSearchQuery = ref('');
const onProductSearch = async (search, loading) => {
  productSearchQuery.value = search;
  if (!search.trim()) {
    let initialList = [];
    if (form.value.product_id && productRegistry.value[form.value.product_id]) {
      initialList.push(productRegistry.value[form.value.product_id]);
    }
    products.value = initialList;
    return;
  }
  try {
    const response = await axios.get(`/product/search?query=${encodeURIComponent(search)}`);
    response.data.forEach(p => {
      productRegistry.value[p.id] = p;
    });
    let results = response.data;
    if (form.value.product_id && productRegistry.value[form.value.product_id]) {
      const alreadyInResults = results.some(p => p.id === form.value.product_id);
      if (!alreadyInResults) {
        results.push(productRegistry.value[form.value.product_id]);
      }
    }
    products.value = results;
  } catch (error) {
    console.error("Error fetching products:", error);
  }
};

// Auto-focus product select on page load
onMounted(() => {
  nextTick(() => {
    const firstInput = document.querySelector('.vs__search');
    if (firstInput) {
      firstInput.focus();
    }
  });
  onProductSearch('', null);
});

const selectedProduct = ref(null);

watch(() => form.value.product_id, (newVal) => {
    if (!newVal) {
        selectedProduct.value = null;
        return;
    }
    selectedProduct.value = products.value.find(p => p.id === newVal) || null;

    nextTick(() => {
        const qtyInput = document.querySelector('input[placeholder="Qty to adjust"]');
        if (qtyInput) {
            qtyInput.focus();
            qtyInput.select();
        }
    });
});
watch(() => form.value.type, () => {
    form.value.reason = "";
});

const reasons = computed(() => {
  if (form.value.type === 'Addition') {
    return [
      { value: 'Physical Count Correction', label: 'Physical Count Correction' },
      { value: 'Restocking', label: 'Restocking' },
      { value: 'Other', label: 'Other' }
    ];
  } else {
    return [
      { value: 'Physical Count Correction', label: 'Physical Count Correction' },
      { value: 'Damaged Goods', label: 'Damaged Goods' },
      { value: 'Expired Stock', label: 'Expired Stock' },
      { value: 'Theft/Loss', label: 'Theft / Loss' },
      { value: 'Other', label: 'Other' }
    ];
  }
});

// Calculate projected stock
const projectedStock = computed(() => {
    if (!selectedProduct.value) return null;
    const current = parseInt(selectedProduct.value.stock_quantity || 0);
    const adjustQty = parseInt(form.value.quantity || 0);
    
    if (form.value.type === 'Addition') {
        return current + adjustQty;
    } else {
        return current - adjustQty;
    }
});

const submitForm = async () => {
  const { product_id, type, quantity, reason } = form.value;

  if (!product_id) {
      toast.error("Please select a product.");
      return;
  }
  if (!quantity || parseInt(quantity) <= 0) {
      toast.error("Please enter a valid adjustment quantity greater than 0.");
      return;
  }
  if (!type) {
      toast.error("Please select the adjustment type.");
      return;
  }
  if (!reason) {
      toast.error("Please select a reason.");
      return;
  }

  // Double check negative stock
  if (type === 'Deduction' && projectedStock.value < 0) {
      toast.error("Cannot deduct stock below 0. Insufficient stock quantity.");
      return;
  }

  try {
    const response = await axios.post(`/stock-adjustment/store`, form.value);
    toast.success(response.data.message || "Stock adjusted successfully!");
    
    // Redirect to list page
    router.visit(route('stock-adjustment.index'));
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred while adjusting stock.";
    toast.error(errorMessage);
  }
};
</script>

<template>
    <Head title="Adjust Stock">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6 max-w-2xl mx-auto border border-gray-100">
        <div class="main-back-class text-sm">
            <a :href="route('stock-adjustment.index')" class="text-gray-500 hover:text-gray-700 flex items-center gap-1">
                <i class="bi bi-chevron-left"></i><span>Back to Audit Ledger</span>
            </a>
        </div>
        
        <h2 class="text-2xl font-bold text-[#292688]">Manual Stock Adjustment</h2>
        <p class="text-xs text-gray-500">Record manual inventory updates due to audit logs correction, damage, expiration, or theft.</p>

        <form @submit.prevent="submitForm" class="space-y-6 mt-4">
            <!-- Product Selector -->
            <div>
                <label class="block text-black font-medium mb-2">Select Product <span class="text-red-500">*</span></label>
                <vSelect
                    v-model="form.product_id"
                    :options="products"
                    label="name"
                    :reduce="product => product.id"
                    placeholder="Search product name or SKU"
                    class="w-full text-black bg-white"
                    @search="onProductSearch"
                    @keydown.enter="moveToNextInput"
                />
            </div>

            <!-- Current & Projected Stock Summary -->
            <div v-if="selectedProduct" class="p-4 rounded-xl border border-gray-200 bg-gray-50 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-gray-500 text-xs font-semibold uppercase">Current Stock</span>
                    <span class="text-lg font-bold text-gray-800">{{ selectedProduct.stock_quantity || 0 }} {{ selectedProduct.unit_type || 'units' }}</span>
                </div>
                <div>
                    <span class="block text-gray-500 text-xs font-semibold uppercase">Projected Stock</span>
                    <span :class="{'text-red-600': projectedStock < 0, 'text-green-600': projectedStock >= (selectedProduct.stock_quantity || 0), 'text-gray-800': projectedStock < (selectedProduct.stock_quantity || 0)}" class="text-lg font-bold">
                        {{ projectedStock }} {{ selectedProduct.unit_type || 'units' }}
                    </span>
                </div>
            </div>

            <!-- Adjustment Direction -->
            <div>
                <label class="block text-black font-medium mb-2">Adjustment Type <span class="text-red-500">*</span></label>
                <div class="flex gap-4">
                    <label class="flex-1 flex items-center justify-center gap-2 border rounded-xl p-3 cursor-pointer transition"
                           :class="form.type === 'Addition' ? 'bg-green-50 border-green-500 text-green-700 font-semibold' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" value="Addition" v-model="form.type" class="text-green-600 focus:ring-green-500 border-gray-300">
                        <span>➕ Add Stock</span>
                    </label>
                    <label class="flex-1 flex items-center justify-center gap-2 border rounded-xl p-3 cursor-pointer transition"
                           :class="form.type === 'Deduction' ? 'bg-red-50 border-red-500 text-red-700 font-semibold' : 'border-gray-200 hover:bg-gray-50'">
                        <input type="radio" value="Deduction" v-model="form.type" class="text-red-600 focus:ring-red-500 border-gray-300">
                        <span>➖ Deduct Stock</span>
                    </label>
                </div>
            </div>

            <!-- Quantity & Reason -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Quantity <span class="text-red-500">*</span></label>
                    <input type="number" v-model.number="form.quantity" min="1" required
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-2 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Qty to adjust" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Reason <span class="text-red-500">*</span></label>
                    <select v-model="form.reason" required
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-2 bg-white text-black border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Reason</option>
                        <option v-for="reason in reasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
                    </select>
                </div>
            </div>

            <!-- Remarks -->
            <div>
                <label class="block text-black font-medium mb-2">Remarks / Notes</label>
                <textarea v-model="form.remarks" rows="3"
                    @keydown.enter.prevent="moveToNextInput"
                    class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#292688] focus:outline-none transition text-sm"
                    placeholder="Enter detailed remarks (e.g. Audit reference number, damage details)..."></textarea>
            </div>

            <!-- Action buttons -->
            <div class="pt-4 flex gap-3">
                <button type="submit" class="flex-1 bg-[#2E2C92] hover:bg-[#201e6a] text-white py-2.5 rounded-lg font-semibold shadow-md transition duration-200">
                    Apply Adjustment
                </button>
                <a :href="route('stock-adjustment.index')" class="px-4 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-100 font-semibold text-gray-700 transition duration-200 text-center text-sm">
                    Cancel
                </a>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>
