<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const suppliers = ref([]);
const supplierSearchQuery = ref("");

const page = usePage();
const showSupplierModal = ref(false);
const newSupplier = ref({
    name: "",
    email: "",
    phone: "",
    gstin: "",
    pan_number: "",
    cin_number: "",
    address: "",
    city: "",
    district: "",
    state: "",
    country: "",
    pin_code: "",
});

const lastActiveElement = ref(null);
const newSupplierNameInput = ref(null);

const handleEscKey = (e) => {
  if (e.key === 'Escape' || e.key === 'Esc') {
    showSupplierModal.value = false;
  }
};

watch(showSupplierModal, async (isOpen) => {
  if (isOpen) {
    window.addEventListener('keydown', handleEscKey);
    lastActiveElement.value = document.activeElement;
    await nextTick();
    if (newSupplierNameInput.value) {
      newSupplierNameInput.value.focus();
    }
  } else {
    window.removeEventListener('keydown', handleEscKey);
    if (lastActiveElement.value) {
      await nextTick();
      lastActiveElement.value.focus();
      lastActiveElement.value = null;
    }
  }
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleEscKey);
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

const onSupplierEnterKey = (event) => {
  if (supplierSearchQuery.value.trim() && suppliers.value.length === 0) {
    event.preventDefault();
    openSupplierModalWithName(supplierSearchQuery.value);
    return;
  }
  moveToNextInput(event);
};

onMounted(() => {
  nextTick(() => {
    const searchInput = document.querySelector('.vs__search');
    if (searchInput) {
      searchInput.focus();
    }
  });
});

const availableDistricts = computed(() => {
    if (!newSupplier.value.state) return [];
    const stateName = newSupplier.value.state;
    const statesData = page.props.state_cities || {};
    const lookupKey = Object.keys(statesData).find(
        key => key.toLowerCase().replace(/[^a-z0-9]/g, '') === stateName.toLowerCase().replace(/[^a-z0-9]/g, '')
    );
    return lookupKey ? statesData[lookupKey] : [];
});

watch(() => newSupplier.value.country, (newVal) => {
    if (newVal && newVal !== 'India') {
        newSupplier.value.state = "";
        newSupplier.value.district = "";
    }
});

watch(() => newSupplier.value.state, (newVal, oldVal) => {
    if (oldVal !== undefined) {
        newSupplier.value.district = "";
        newSupplier.value.city = "";
    }
    if (newVal && (!newSupplier.value.country || newSupplier.value.country === 'India')) {
        newSupplier.value.country = "India";
        
        // Modal State ➔ District auto-focus
        nextTick(() => {
            const modalDropdowns = document.querySelectorAll('form .v-select .vs__search');
            if (modalDropdowns.length > 1) {
                modalDropdowns[1].focus();
            }
        });
    }
});

watch(() => newSupplier.value.district, (newVal) => {
    if (newVal) {
        // Modal District ➔ City focus
        nextTick(() => {
            const cityInput = document.querySelector('form input[v-model="newSupplier.city"]') || document.querySelectorAll('form input')[5];
            if (cityInput) {
                cityInput.focus();
            }
        });
    }
});

const openSupplierModalWithName = (search) => {
    newSupplier.value = {
        name: search || "",
        email: "",
        phone: "",
        gstin: "",
        pan_number: "",
        cin_number: "",
        address: "",
        city: "",
        district: "",
        state: "",
        country: "",
        pin_code: "",
    };
    showSupplierModal.value = true;
};

const submitSupplier = async () => {
    try {
        if (!newSupplier.value.name) {
            toast.error("Supplier name is required!");
            return;
        }

        const response = await axios.post('/supplier/store', newSupplier.value);
        const createdSupplier = response.data;
        suppliers.value.push(createdSupplier);
        form.value.supplier_id = createdSupplier.id;
        showSupplierModal.value = false;
        toast.success("Supplier added successfully!");
    } catch (error) {
        const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
        if (typeof errorMessage === 'object') {
            Object.values(errorMessage).forEach(msgArray => {
                if (Array.isArray(msgArray)) msgArray.forEach(m => toast.error(m));
                else toast.error(msgArray);
            });
        } else {
            toast.error(errorMessage);
        }
    }
};

const today = new Date().toISOString().split('T')[0];

const form = ref({
    supplier_id: "",
    amount: "",
    payment_date: today,
    payment_method: "",
    note: "",
});

const onSupplierSearch = async (search, loading) => {
  supplierSearchQuery.value = search;
  if (!search.trim()) {
    if (form.value.supplier_id) {
      const selected = suppliers.value.find(s => s.id === form.value.supplier_id);
      suppliers.value = selected ? [selected] : [];
    } else {
      suppliers.value = [];
    }
    return;
  }
  try {
    const response = await axios.get(`/supplier/search?query=${encodeURIComponent(search)}`);
    suppliers.value = response.data;
  } catch (error) {
    console.error("Error fetching suppliers:", error);
  }
};

// Submit the form data
const submitForm = async () => {
  try {
    const response = await axios.post(`/paymentSupplier/store`, form.value);
    toast.success(response.data.message);

    // Reset the form
    form.value = {
      supplier_id: "",
      amount: "",
      payment_date: today,
      payment_method: "",
      note: "",
    };
    suppliers.value = [];
    supplierSearchQuery.value = "";
    nextTick(() => {
      const searchInput = document.querySelector('.vs__search');
      if (searchInput) {
        searchInput.focus();
      }
    });
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};

watch(() => form.value.supplier_id, (newVal) => {
  if (newVal) {
    nextTick(() => {
      const amountInput = document.querySelector('input[name="amount"]');
      if (amountInput) {
        amountInput.focus();
        amountInput.select();
      }
    });
  }
});
</script>
<template>

    <Head title="Supplier Payment">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
        <div class="main-back-class">
            <a :href="route('paymentSupplier')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Supplier Payment</span></a>
        </div>
            <h2 class="text-2xl font-bold mb-4 text-[#292688]">Add Payment</h2>
        <form @submit.prevent="submitForm">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-black font-medium mb-2">Supplier <span class="text-red-500">*</span></label>
                    <vSelect
                        v-model="form.supplier_id"
                        :options="suppliers"
                        label="name"
                        :reduce="supplier => supplier.id"
                        placeholder="Search or select supplier"
                        class="w-full text-black bg-white"
                        @search="onSupplierSearch"
                        @keydown.enter="onSupplierEnterKey"
                    >
                        <template #no-options="{ search, searching, loading }">
                            <div class="px-3 py-2 text-gray-500 flex items-center justify-between">
                                <span v-if="!search">Type to search supplier...</span>
                                <span v-else>No suppliers found.</span>
                                <button
                                    type="button"
                                    @click.stop="openSupplierModalWithName(search)"
                                    :class="search
                                            ? 'mt-2 inline-flex items-center text-blue-600 text-sm font-semibold border border-blue-300 rounded-lg px-3 py-1.5'
                                            : 'mt-2 inline-flex items-center text-blue-600 text-sm font-semibold'"
                                    >
                                    ➕ Add New Supplier
                                </button>
                            </div>
                        </template>
                    </vSelect>
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Amount <span class="text-red-500">*</span></label>
                    <input type="number" step="any" name="amount" v-model="form.amount"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Amount" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" v-model="form.payment_date"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Payment Date" />
                </div>

                <div>
                    <label class="block text-black font-medium mb-2">Payment Method <span class="text-red-500">*</span></label>
                    <select name="payment_method" v-model="form.payment_method"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Payment Method</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="UPI">UPI</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">

                <div>
                    <label class="block text-black font-medium mb-2">Note</label>
                    <input type="note" name="note" v-model="form.note"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Note"/>
                </div>
            </div>

            <div class="pt-4">
            <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Save</button>
            </div>
        </form>
    </div>

    <!-- Add Supplier Modal -->
    <div v-if="showSupplierModal"
        class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
        style="z-index: 99999;"
        @click.self="showSupplierModal = false">
        <form @submit.prevent="submitSupplier" class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-2xl my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
            <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                <h2 class="text-xl font-bold text-[#2E2C92]">Add New Supplier</h2>
                <button type="button" @click="showSupplierModal = false" class="text-gray-400 hover:text-gray-600 transition">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input ref="newSupplierNameInput" type="text" v-model="newSupplier.name" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" v-model="newSupplier.email" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone No</label>
                        <input type="text" v-model="newSupplier.phone" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" v-model="newSupplier.address" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                        <select v-model="newSupplier.country" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none bg-white">
                            <option value="" disabled>Select Country</option>
                            <option v-for="c in $page.props.countries" :key="c" :value="c">
                                {{ c }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <vSelect
                            v-if="!newSupplier.country || newSupplier.country === 'India'"
                            :options="$page.props.gst_states"
                            label="display"
                            :reduce="state => state.name"
                            v-model="newSupplier.state"
                            placeholder="Search & Select State"
                            class="w-full text-black bg-white"
                        ></vSelect>
                        <input
                            v-else
                            type="text"
                            v-model="newSupplier.state"
                            class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none"
                            placeholder="Enter State"
                        />
                    </div>
                    <div v-if="!newSupplier.country || newSupplier.country === 'India'">
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <vSelect
                            :options="availableDistricts"
                            v-model="newSupplier.district"
                            placeholder="Search & Select District"
                            class="w-full text-black bg-white"
                            :disabled="!newSupplier.state"
                        ></vSelect>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PIN Code</label>
                        <input type="text" v-model="newSupplier.pin_code" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CIN Number</label>
                        <input type="text" v-model="newSupplier.cin_number" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">GSTIN</label>
                        <input type="text" v-model="newSupplier.gstin" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" placeholder="e.g. 22AAAAA1111A1Z1" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">PAN Number</label>
                        <input type="text" v-model="newSupplier.pan_number" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#2E2C92] focus:outline-none" placeholder="Enter PAN Number" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-2">
                <button
                    @click="showSupplierModal = false"
                    type="button"
                    class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition cursor-pointer"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-xl shadow-md transition cursor-pointer"
                >
                    Save Supplier
                </button>
            </div>
        </form>
    </div>
    </AuthenticatedLayout>
</template>

<style>
.v-select .vs__dropdown-toggle {
    min-height: 50px;
    border-radius: 0.75rem !important;
    border-color: #d1d5db;
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}
.v-select .vs__selected, .v-select .vs__search {
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1.5;
}
</style>

