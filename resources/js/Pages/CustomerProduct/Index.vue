<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import Swal from 'sweetalert2';
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const props = defineProps({
  customers: {
    type: Array,
    default: () => [],
  },
  selectedCustomerId: {
    type: [Number, String],
    default: null,
  },
  selectedCustomer: {
    type: Object,
    default: null,
  },
  customerProducts: {
    type: Array,
    default: () => [],
  },
  masterProducts: {
    type: Array,
    default: () => [],
  },
});

// Current Customer state
const currentCustomerId = ref(props.selectedCustomerId);
const currentCustomer = ref(props.selectedCustomer);
const productsList = ref([...props.customerProducts]);

// Watch for prop updates
watch(() => props.customerProducts, (newVal) => {
  productsList.value = [...newVal];
  selectedRowIds.value = [];
});

watch(() => props.selectedCustomer, (newVal) => {
  currentCustomer.value = newVal;
});

watch(() => props.selectedCustomerId, (newVal) => {
  currentCustomerId.value = newVal;
});

// Search within customer's assigned products
const localSearch = ref('');
const filteredProducts = computed(() => {
  if (!localSearch.value.trim()) return productsList.value;
  const q = localSearch.value.toLowerCase().trim();
  return productsList.value.filter(p =>
    (p.name && p.name.toLowerCase().includes(q)) ||
    (p.sku && p.sku.toLowerCase().includes(q)) ||
    (p.category_name && p.category_name.toLowerCase().includes(q))
  );
});

// When user changes customer in the selector
const onCustomerChange = (customer) => {
  if (customer && customer.id) {
    router.get(route('customer-product.index'), { customer_id: customer.id }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }
};

// Selection for bulk delete
const selectedRowIds = ref([]);
const isAllSelected = computed(() => {
  return filteredProducts.value.length > 0 && selectedRowIds.value.length === filteredProducts.value.length;
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedRowIds.value = [];
  } else {
    selectedRowIds.value = filteredProducts.value.map(p => p.id);
  }
};

const toggleSelectRow = (id) => {
  const idx = selectedRowIds.value.indexOf(id);
  if (idx > -1) {
    selectedRowIds.value.splice(idx, 1);
  } else {
    selectedRowIds.value.push(id);
  }
};

// ==================== ASSIGN PRODUCTS MODAL ====================
const showAddModal = ref(false);
const masterSearchQuery = ref('');
const candidateItems = ref([]); // { product_id, name, sku, master_price, sale_price, unit_type, selected }
const isSavingAssignment = ref(false);

const openAddModal = () => {
  if (!currentCustomer.value) {
    toast.warning("Please select a customer first.");
    return;
  }
  masterSearchQuery.value = '';
  // Exclude products that are already assigned to this customer
  const assignedProductIds = new Set(productsList.value.map(p => p.product_id));

  candidateItems.value = props.masterProducts.map(p => ({
    product_id: p.id,
    name: p.name,
    sku: p.sku,
    category_name: p.category_name,
    master_price: p.price,
    sale_price: p.price, // Default custom price to master price
    unit_type: p.unit_type,
    is_already_assigned: assignedProductIds.has(p.id),
    selected: false,
  }));

  showAddModal.value = true;
};

const closeAddModal = () => {
  showAddModal.value = false;
  candidateItems.value = [];
  masterSearchQuery.value = '';
};

// Only show products when user actually enters a search query
const filteredCandidateItems = computed(() => {
  if (!masterSearchQuery.value.trim()) return [];
  const q = masterSearchQuery.value.toLowerCase().trim();
  return candidateItems.value.filter(item =>
    !item.is_already_assigned &&
    ((item.name && item.name.toLowerCase().includes(q)) ||
      (item.sku && item.sku.toLowerCase().includes(q)) ||
      (item.category_name && item.category_name.toLowerCase().includes(q)))
  );
});

// Assign single product directly for its price
const assignSingleProduct = async (item) => {
  const price = parseFloat(item.sale_price);
  if (isNaN(price) || price < 0) {
    toast.error("Please enter a valid price (minimum 0).");
    return;
  }

  item.is_saving = true;
  try {
    const res = await axios.post(route('customer-product.store'), {
      customer_id: currentCustomer.value.id,
      items: [{
        product_id: item.product_id,
        sale_price: price,
      }]
    });

    toast.success(res.data.message || `${item.name} assigned at ₹${price.toFixed(2)}`);
    item.is_already_assigned = true;
    router.reload({ only: ['customerProducts', 'customers'] });
  } catch (err) {
    console.error("Error assigning product:", err);
    toast.error(err.response?.data?.message || "Failed to assign product.");
  } finally {
    item.is_saving = false;
  }
};

// ==================== EDIT PRICE MODAL ====================
const showEditPriceModal = ref(false);
const editingItem = ref(null);
const newSalePrice = ref('');
const isSavingPrice = ref(false);

const openEditPriceModal = (item) => {
  editingItem.value = item;
  newSalePrice.value = item.sale_price;
  showEditPriceModal.value = true;
};

const closeEditPriceModal = () => {
  showEditPriceModal.value = false;
  editingItem.value = null;
};

const submitUpdatePrice = async () => {
  if (!editingItem.value) return;
  const price = parseFloat(newSalePrice.value);
  if (isNaN(price) || price < 0) {
    toast.error("Please enter a valid price (minimum 0).");
    return;
  }

  isSavingPrice.value = true;
  try {
    const res = await axios.post(route('customer-product.update', editingItem.value.id), {
      sale_price: price,
    });

    // Update in local state instantly
    editingItem.value.sale_price = price;
    toast.success("Sale price updated successfully!");
    closeEditPriceModal();
  } catch (err) {
    console.error("Error updating price:", err);
    toast.error(err.response?.data?.message || "Failed to update price.");
  } finally {
    isSavingPrice.value = false;
  }
};

// ==================== DELETE / REMOVE PRODUCT ====================
const deleteProduct = (item) => {
  Swal.fire({
    title: 'Remove Product?',
    html: `Do you want to remove <strong>${item.name}</strong> from this customer's product list?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, remove',
    cancelButtonText: 'Cancel'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        const res = await axios.delete(route('customer-product.destroy', item.id));
        toast.success(res.data.message || "Product removed from customer.");
        productsList.value = productsList.value.filter(p => p.id !== item.id);
        router.reload({ only: ['customerProducts', 'customers'] });
      } catch (err) {
        toast.error("Failed to remove product.");
      }
    }
  });
};

const bulkDeleteProducts = () => {
  if (selectedRowIds.value.length === 0) return;

  Swal.fire({
    title: 'Remove Selected Products?',
    text: `Do you want to remove ${selectedRowIds.value.length} selected product(s) from this customer?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, remove them'
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        const res = await axios.post(route('customer-product.bulk-delete'), {
          ids: selectedRowIds.value
        });
        toast.success(res.data.message || "Selected products removed successfully.");
        selectedRowIds.value = [];
        router.reload({ only: ['customerProducts', 'customers'] });
      } catch (err) {
        toast.error("Failed to remove selected products.");
      }
    }
  });
};

const handleStorageSync = (event) => {
  if (event.key === 'customer_pricing_toggle_sync') {
    try {
      const data = JSON.parse(event.newValue);
      if (data && data.enabled === false) {
        window.location.reload();
      }
    } catch (e) { }
  }
};

const handleWindowFocus = () => {
  try {
    const sync = localStorage.getItem('customer_pricing_toggle_sync');
    if (sync) {
      const data = JSON.parse(sync);
      if (data && data.enabled === false) {
        window.location.reload();
      }
    }
  } catch (e) { }
};

const isReportDropdownOpen = ref(false);
const reportDropdownRef = ref(null);

const closeOnEscape = (e) => {
  if (isReportDropdownOpen.value && e.key === 'Escape') {
    isReportDropdownOpen.value = false;
  }
};

onMounted(() => {
  window.addEventListener('storage', handleStorageSync);
  window.addEventListener('focus', handleWindowFocus);
  document.addEventListener('keydown', closeOnEscape);
});

onUnmounted(() => {
  window.removeEventListener('storage', handleStorageSync);
  window.removeEventListener('focus', handleWindowFocus);
  document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>

  <Head title="Customer Products & Pricing" />

  <AuthenticatedLayout>

    <div class="py-6 mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-person-lines-fill text-indigo-600"></i>
            <span>Customer Products & Pricing</span>
          </h2>
          <p class="text-sm text-gray-500 mt-0.5">
            Manage customer-specific product catalog and customized selling rates
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-3">
          <!-- Download Report Dropdown -->
          <div v-if="currentCustomer" class="relative" ref="reportDropdownRef">
            <button
              type="button"
              @click="isReportDropdownOpen = !isReportDropdownOpen"
              class="relative z-50 inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 text-sm font-semibold rounded-lg shadow-sm transition-all duration-150 cursor-pointer"
            >
              <i class="fa fa-download text-indigo-600"></i>
              <span>Download Report</span>
              <i class="bi bi-chevron-down text-xs transition-transform duration-150" :class="{ 'rotate-180': isReportDropdownOpen }"></i>
            </button>

            <!-- Full Screen Dropdown Overlay to close on side click -->
            <div
              v-show="isReportDropdownOpen"
              class="fixed inset-0 z-40"
              @click="isReportDropdownOpen = false"
            ></div>

            <!-- Dropdown Menu -->
            <div
              v-show="isReportDropdownOpen"
              class="absolute right-0 mt-2 w-60 bg-white border border-slate-200 rounded-xl shadow-xl py-2 z-50 text-xs"
            >
              <div class="px-3.5 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                {{ currentCustomer.name }}
              </div>
              <a
                :href="`/customer-product/download-pdf?customer_id=${currentCustomer.id}`"
                target="_blank"
                @click="isReportDropdownOpen = false"
                class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 hover:bg-indigo-50 hover:text-[#2e2c92] transition"
              >
                <i class="fa fa-file-pdf-o text-rose-500 text-sm w-4 text-center"></i>
                <div>
                  <div class="font-semibold text-xs">Customer Price List (PDF)</div>
                  <div class="text-[10px] text-slate-400">Printable price sheet for this customer</div>
                </div>
              </a>
              <a
                :href="`/customer-product/download-csv?customer_id=${currentCustomer.id}`"
                @click="isReportDropdownOpen = false"
                class="flex items-center gap-2.5 px-3.5 py-2 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition"
              >
                <i class="fa fa-file-excel-o text-emerald-600 text-sm w-4 text-center"></i>
                <div>
                  <div class="font-semibold text-xs">Customer Price List (CSV)</div>
                  <div class="text-[10px] text-slate-400">Excel spreadsheet for this customer</div>
                </div>
              </a>
            </div>
          </div>

          <button v-if="currentCustomer" @click="openAddModal"
            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold rounded-lg shadow-sm transition-all duration-150 cursor-pointer">
            <i class="bi bi-plus-circle text-base"></i>
            <span>Add Products to Customer</span>
          </button>
        </div>
      </div>

      <!-- Customer Selector & Info Bar Card -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200/80 p-4">
        <div class="flex flex-col md:flex-row items-stretch gap-4">

          <!-- Customer Dropdown Selector -->
          <div
            class="w-full md:w-[400px] lg:w-[600px] shrink-0 flex flex-col justify-center bg-slate-50 border border-slate-200/70 rounded-xl p-3.5">
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-1.5">
              Select Customer
            </label>
            <v-select :options="customers" :modelValue="currentCustomer" @update:modelValue="onCustomerChange"
              label="name" placeholder="Search & choose customer..." class="erp-vselect bg-white rounded-lg">
              <template #option="option">
                <div class="py-1 flex items-center justify-between">
                  <div>
                    <div class="font-medium text-gray-900">{{ option.name }}</div>
                    <div class="text-xs text-gray-500">{{ option.phone || 'No phone' }} <span v-if="option.city">• {{
                      option.city }}</span></div>
                  </div>
                  <span
                    :class="option.customer_products_count > 0 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'"
                    class="text-[11px] px-2 py-0.5 rounded-full font-semibold">
                    {{ option.customer_products_count || 0 }} products
                  </span>
                </div>
              </template>
              <template #selected-option="option">
                <div class="flex items-center gap-2">
                  <span class="font-semibold text-gray-800">{{ option.name }}</span>
                  <span v-if="option.phone" class="text-xs text-gray-500">({{ option.phone }})</span>
                </div>
              </template>
            </v-select>
          </div>

          <!-- Selected Customer Quick Details -->
          <div v-if="currentCustomer"
            class="flex-1 flex flex-wrap items-center justify-between bg-slate-50 border border-slate-200/70 rounded-xl p-3.5 gap-4">
            <div class="flex items-center gap-3.5">
              <div
                class="w-11 h-11 rounded-full bg-gradient-to-br from-[#2E2C92] to-indigo-600 text-white flex items-center justify-center font-bold text-lg shadow-sm shrink-0">
                {{ currentCustomer.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <h4 class="font-bold text-gray-900 text-sm leading-tight flex items-center gap-2">
                  <span>{{ currentCustomer.name }}</span>
                  <span class="text-xs font-normal text-gray-500" v-if="currentCustomer.phone">
                    <i class="bi bi-telephone text-[11px] text-indigo-500"></i> {{ currentCustomer.phone }}
                  </span>
                </h4>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 text-xs text-gray-500 mt-1">
                  <span v-if="currentCustomer.email" class="flex items-center gap-1 truncate max-w-[220px]"
                    :title="currentCustomer.email">
                    <i class="bi bi-envelope text-[11px] text-gray-400"></i> {{ currentCustomer.email }}
                  </span>
                  <span v-if="currentCustomer.city || currentCustomer.state" class="flex items-center gap-1">
                    <i class="bi bi-geo-alt text-[11px] text-gray-400"></i> {{ [currentCustomer.city,
                    currentCustomer.state].filter(Boolean).join(', ') }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Stats badges -->
            <div class="flex items-center gap-2 shrink-0">
              <div class="text-center px-4 py-2 bg-white border border-indigo-200 rounded-lg shadow-2xs">
                <span class="text-xxs text-indigo-600 font-semibold uppercase tracking-wider block">Assigned
                  Products</span>
                <span class="text-xl font-extrabold text-[#2E2C92] leading-tight">{{ productsList.length }}</span>
              </div>
            </div>
          </div>

          <!-- Empty prompt when no customer is selected -->
          <div v-else
            class="flex-1 flex items-center justify-center bg-slate-50 border border-dashed border-slate-200 rounded-xl p-4 text-sm text-slate-500 italic">
            <i class="bi bi-arrow-left mr-2 text-indigo-500"></i> Select a customer to view and manage their customized
            products
            and sale prices.
          </div>

        </div>
      </div>

      <!-- Main Products Table Card (Shown when customer is selected) -->
      <div v-if="currentCustomer" class="bg-white rounded-xl shadow-sm border border-gray-200/80 overflow-hidden">

        <!-- Table Toolbar -->
        <div
          class="p-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gray-50/50">
          <div class="flex items-center gap-3">
            <div class="relative w-full sm:w-72">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                <i class="bi bi-search"></i>
              </span>
              <input v-model="localSearch" type="text" placeholder="Search assigned products..."
                class="w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
            </div>

            <button v-if="selectedRowIds.length > 0" @click="bulkDeleteProducts"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg transition-colors">
              <i class="bi bi-trash"></i>
              <span>Remove Selected ({{ selectedRowIds.length }})</span>
            </button>
          </div>

          <div class="text-xs text-gray-500">
            Showing <span class="font-semibold text-gray-800">{{ filteredProducts.length }}</span> of <span
              class="font-semibold text-gray-800">{{ productsList.length }}</span> product(s)
          </div>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-sm">
            <thead>
              <tr class="bg-[#1e1b4b] text-white uppercase text-[11px] font-semibold tracking-wider">
                <th class="py-3 px-4 w-10 text-center">
                  <input type="checkbox" :checked="isAllSelected" @change="toggleSelectAll"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                </th>
                <th class="py-3 px-3 w-12 text-center">S.No</th>
                <th class="py-3 px-4">Product Name</th>
                <th class="py-3 px-4">Category</th>
                <th class="py-3 px-4">Standard Price</th>
                <th class="py-3 px-4">Customer Sale Price</th>
                <th class="py-3 px-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(item, index) in filteredProducts" :key="item.id"
                class="hover:bg-indigo-50/40 transition-colors">
                <!-- Checkbox -->
                <td class="py-3 px-4 text-center">
                  <input type="checkbox" :checked="selectedRowIds.includes(item.id)" @change="toggleSelectRow(item.id)"
                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer" />
                </td>

                <!-- S.No -->
                <td class="py-3 px-3 text-center text-gray-500 font-medium text-xs">
                  {{ index + 1 }}
                </td>

                <!-- Product Name -->
                <td class="py-3 px-4">
                  <span class="font-semibold text-gray-900 block">{{ item.name }}</span>
                </td>

                <!-- Category -->
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                    {{ item.category_name }}
                  </span>
                </td>

                <!-- Master Standard Price -->
                <td class="py-3 px-4 text-gray-600 font-medium">
                  ₹{{ item.master_price.toFixed(2) }}
                </td>

                <!-- Customer Sale Price (Special Price) -->
                <td class="py-3 px-4">
                  <span
                    class="font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200 inline-block">
                    ₹{{ item.sale_price.toFixed(2) }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-center whitespace-nowrap">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="openEditPriceModal(item)"
                      class="p-1.5 text-blue-600 hover:text-white hover:bg-blue-600 rounded transition-colors"
                      title="Edit Special Price">
                      <i class="bi bi-pencil text-sm"></i>
                    </button>
                    <button @click="deleteProduct(item)"
                      class="p-1.5 text-red-600 hover:text-white hover:bg-red-600 rounded transition-colors"
                      title="Remove from Customer">
                      <i class="bi bi-trash text-sm"></i>
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty state within table -->
              <tr v-if="filteredProducts.length === 0">
                <td colspan="7" class="py-12 text-center">
                  <div class="max-w-sm mx-auto">
                    <div
                      class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl">
                      <i class="bi bi-box-seam"></i>
                    </div>
                    <h4 class="font-bold text-gray-800 text-base mb-1">No products added for this customer</h4>
                    <p class="text-xs text-gray-500 mb-4">
                      Add products from master inventory to give this customer custom selling rates.
                    </p>
                    <button @click="openAddModal"
                      class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm">
                      <i class="bi bi-plus-circle"></i>
                      <span>Add Products Now</span>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- No Customer Selected Overview Card -->
      <div v-else class="bg-white rounded-xl shadow-sm border border-gray-200/80 p-8 text-center">
        <div
          class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-4 text-2xl">
          <i class="bi bi-people"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">Select a Customer to Manage Products</h3>
        <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
          Choose any customer from the selector above to assign them products and set their custom sales pricing.
        </p>
      </div>

    </div>

    <!-- ==================== ADD PRODUCTS MODAL ==================== -->
    <div v-if="showAddModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
      <div
        class="bg-white rounded-2xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-150">

        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
          <div>
            <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
              <i class="bi bi-plus-circle-fill text-indigo-600"></i>
              <span>Assign Products to {{ currentCustomer?.name }}</span>
            </h3>
            <p class="text-xs text-gray-500 mt-0.5">
              Select products from master inventory and set their customer-specific sale prices.
            </p>
          </div>
          <button @click="closeAddModal" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-200">
            <i class="bi bi-x-lg text-lg"></i>
          </button>
        </div>

        <!-- Modal Search Bar -->
        <div class="px-6 py-3.5 border-b border-gray-200 bg-white">
          <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
              <i class="bi bi-search text-base"></i>
            </span>
            <input v-model="masterSearchQuery" type="text"
              placeholder="Search master products by name, SKU, or category..."
              class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
              autofocus />
          </div>
        </div>

        <!-- Products Selection Body -->
        <div class="flex-1 overflow-y-auto p-6 max-h-[55vh]">
          <!-- When search query is empty: prompt user to search -->
          <div v-if="!masterSearchQuery.trim()" class="py-14 text-center text-gray-500">
            <div
              class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl">
              <i class="bi bi-search"></i>
            </div>
            <h4 class="font-bold text-gray-800 text-sm">Type product name to search</h4>
            <p class="text-xs text-gray-400 mt-1 max-w-sm mx-auto">
              Search a master product above and set its customer sale price to assign it to this customer.
            </p>
          </div>

          <!-- When searching and matches found: table of matching products -->
          <table v-else-if="filteredCandidateItems.length > 0" class="w-full text-left border-collapse text-sm">
            <thead class="sticky top-0 bg-gray-100 text-gray-700 uppercase text-[11px] font-bold z-10">
              <tr>
                <th class="py-2.5 px-3 w-12 text-center">S.No</th>
                <th class="py-2.5 px-3">Product Name</th>
                <th class="py-2.5 px-3">Category</th>
                <th class="py-2.5 px-3">Standard Price</th>
                <th class="py-2.5 px-3 w-44">Customer Sale Price</th>
                <th class="py-2.5 px-3 text-center w-32">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="(item, idx) in filteredCandidateItems" :key="item.product_id"
                class="hover:bg-gray-50 transition-colors">
                <!-- S.No -->
                <td class="py-2.5 px-3 text-center text-xs text-gray-500 font-medium">
                  {{ idx + 1 }}
                </td>

                <!-- Name -->
                <td class="py-2.5 px-3 font-semibold text-gray-900">
                  {{ item.name }}
                </td>

                <!-- Category -->
                <td class="py-2.5 px-3 text-xs text-gray-600">
                  {{ item.category_name }}
                </td>

                <!-- Standard Master Price -->
                <td class="py-2.5 px-3 font-medium text-gray-600 text-sm">
                  ₹{{ item.master_price.toFixed(2) }}
                </td>

                <!-- Customer Sale Price Input -->
                <td class="py-2.5 px-3">
                  <div class="relative rounded-md shadow-2xs">
                    <span
                      class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-gray-400 text-xs font-bold">
                      ₹
                    </span>
                    <input type="number" step="0.01" min="0" v-model="item.sale_price"
                      @keydown.enter.prevent="assignSingleProduct(item)"
                      class="w-full pl-6 pr-2 py-1 text-sm font-semibold rounded-md border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-emerald-700 bg-white"
                      placeholder="Sale price" />
                  </div>
                </td>

                <!-- Action Button -->
                <td class="py-2.5 px-3 text-center">
                  <button type="button" @click="assignSingleProduct(item)" :disabled="item.is_saving"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 rounded-lg shadow-xs transition-colors cursor-pointer">
                    <i v-if="item.is_saving" class="bi bi-arrow-repeat animate-spin"></i>
                    <i v-else class="bi bi-plus-lg"></i>
                    <span>Assign Price</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Empty search results -->
          <div v-else class="py-12 text-center text-gray-400 italic text-sm">
            No unassigned products found matching "{{ masterSearchQuery }}".
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-3.5 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
          <div class="text-xs text-gray-500">
            <i class="bi bi-info-circle"></i> Set the customer sale price and click Assign Price (or press Enter).
          </div>

          <button type="button" @click="closeAddModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg">
            Done / Close
          </button>
        </div>

      </div>
    </div>

    <!-- ==================== EDIT PRICE MODAL ==================== -->
    <div v-if="showEditPriceModal"
      class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
      <div
        class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150">

        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
          <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
            <i class="bi bi-tag-fill text-indigo-600"></i>
            <span>Update Customer Sale Price</span>
          </h3>
          <button @click="closeEditPriceModal" class="text-gray-400 hover:text-gray-600">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="p-6 space-y-4">
          <div>
            <span class="text-xs font-semibold text-gray-500 uppercase">Product</span>
            <div class="text-base font-bold text-gray-900 mt-0.5">{{ editingItem?.name }}</div>
            <div class="text-xs text-gray-500">Standard Master Price: ₹{{ editingItem?.master_price.toFixed(2) }}</div>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1.5">
              Customer Special Sale Price (₹)
            </label>
            <div class="relative rounded-lg shadow-2xs">
              <span
                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500 font-bold">
                ₹
              </span>
              <input type="number" step="0.01" min="0" v-model="newSalePrice"
                class="w-full pl-8 pr-3 py-2 text-base font-bold rounded-lg border border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-emerald-700"
                placeholder="0.00" autofocus />
            </div>
            <p class="text-xs text-gray-400 mt-1">
              This price will automatically apply when billing to {{ currentCustomer?.name }}.
            </p>
          </div>
        </div>

        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 flex items-center justify-end gap-3">
          <button type="button" @click="closeEditPriceModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white hover:bg-gray-100 border border-gray-300 rounded-lg">
            Cancel
          </button>
          <button type="button" @click="submitUpdatePrice" :disabled="isSavingPrice"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg shadow-sm">
            <i v-if="isSavingPrice" class="bi bi-arrow-repeat animate-spin"></i>
            <span>Save Price</span>
          </button>
        </div>

      </div>
    </div>

  </AuthenticatedLayout>
</template>

<style scoped>
:deep(.erp-vselect .vs__dropdown-toggle) {
  padding: 6px 8px;
  border-radius: 0.5rem;
  border-color: #d1d5db;
  background-color: #ffffff;
}

:deep(.erp-vselect .vs__dropdown-menu) {
  max-height: 280px;
  border-radius: 0.5rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
</style>
