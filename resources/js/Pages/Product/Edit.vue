<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';
import axios from 'axios';

// Access props
const { productDetail, categories, unitTypes } = usePage().props;
const nameInput = ref(null);

const form = ref({
    name: productDetail.name,
    unit_type: productDetail.unit_type,
    width: productDetail.width || '',
    height: productDetail.height || '',
    alternate_unit_type: productDetail.alternate_unit_type || '',
    hsn_code: productDetail.hsn_code || '',
    price: productDetail.price || '',
    type: productDetail.type || 'product',
    category_id: productDetail.category_id,
    description: productDetail.description,
    image: null,
    remove_image: false,
});

const unitSearchQuery = ref("");
const showUnitDropdown = ref(false);
const imagePreview = ref(productDetail.image ? productDetail.image : null);
const isDragging = ref(false);

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

onMounted(() => {
  if (form.value.unit_type && unitTypes[form.value.unit_type]) {
    unitSearchQuery.value = unitTypes[form.value.unit_type];
  }
  nextTick(() => {
    if (nameInput.value) {
        nameInput.value.focus();
    }
  });
});

const filteredUnitTypes = computed(() => {
  const query = unitSearchQuery.value.toLowerCase().trim();
  if (!query) {
    return unitTypes;
  }
  const filtered = {};
  for (const [key, label] of Object.entries(unitTypes)) {
    if (label.toLowerCase().includes(query) || key.toLowerCase().includes(query)) {
      filtered[key] = label;
    }
  }
  return filtered;
});

const selectUnit = (key, label) => {
  form.value.unit_type = key;
  unitSearchQuery.value = label;
  showUnitDropdown.value = false;
  
  // Auto-focus Sale Price input field
  nextTick(() => {
    const priceInput = document.querySelector('input[name="price"]');
    if (priceInput) {
      priceInput.focus();
      priceInput.select();
    }
  });
};

const closeUnitDropdownWithDelay = () => {
  setTimeout(() => {
    showUnitDropdown.value = false;
    if (form.value.unit_type && unitTypes[form.value.unit_type]) {
      unitSearchQuery.value = unitTypes[form.value.unit_type];
    } else {
      unitSearchQuery.value = "";
    }
  }, 200);
};

watch(() => form.value.unit_type, (newVal) => {
  if (!newVal) {
    unitSearchQuery.value = "";
  } else if (unitTypes[newVal]) {
    unitSearchQuery.value = unitTypes[newVal];
  }
});

// Image Upload Handlers
const handleFileSelect = (file) => {
  if (file && file.type.startsWith('image/')) {
    form.value.image = file;
    form.value.remove_image = false;
    imagePreview.value = URL.createObjectURL(file);
  } else {
    toast.error("Please upload a valid image file.");
  }
};

const onFileChange = (e) => {
  const file = e.target.files[0];
  handleFileSelect(file);
};

const onDragOver = (e) => {
  e.preventDefault();
  isDragging.value = true;
};

const onDragLeave = () => {
  isDragging.value = false;
};

const onDrop = (e) => {
  e.preventDefault();
  isDragging.value = false;
  const file = e.dataTransfer.files[0];
  handleFileSelect(file);
};

const removeImage = () => {
  form.value.image = null;
  form.value.remove_image = true;
  imagePreview.value = null;
};

// Form submit handler
const submitForm = async () => {
    if (!form.value.name) {
        toast.error("Product name is required!");
        return;
    }
    if (!form.value.category_id) {
        toast.error("Category is required!");
        return;
    }
    if (!form.value.unit_type) {
        toast.error("Unit Type is required!");
        return;
    }
    try {
        const formData = new FormData();
        for (const [key, value] of Object.entries(form.value)) {
            if (value !== null && value !== undefined) {
                formData.append(key, value);
            }
        }

        const response = await axios.post(`/product/update/${productDetail.id}`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        toast.success(response.data.message);
    } catch (error) {
        const errorMessage = error.response?.data?.message || 'An error occurred. Please try again.';
        toast.error(errorMessage);
    }
};

const getImageName = () => {
  if (form.value.image) {
    return form.value.image.name;
  }
  if (productDetail.image) {
    const parts = productDetail.image.split('/');
    return parts[parts.length - 1];
  }
  return 'Image Selected';
};
</script>
<template>

    <Head title="Product">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>
        <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
            <div class="main-back-class">
                <a :href="route('product')"><i style="font-size: 14px;" class="bi bi-chevron-left"></i><span style="margin-left: 5px;">Product</span></a>
            </div>
                <h2 class="text-2xl font-bold mb-4 text-[#292688]">Update Product</h2>
            <form @submit.prevent="submitForm">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                    <div>
                        <label class="block text-black font-medium mb-2">Name <span class="text-red-500">*</span></label>
                        <input ref="nameInput" type="text" v-model="form.name" name="name" required
                            @keydown.enter.prevent="moveToNextInput"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Name" />
                    </div>
                    <div>
                        <label class="block text-black font-medium mb-2">Category <span class="text-red-500">*</span></label>
                        <select name="category_id" v-model="form.category_id" required
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="" disabled>Select Category</option>
                            <option v-for="category in categories" :key="category.id"
                                    :value="category.id"> {{ category.name }}</option>
                        </select>
                    </div>
                </div>



                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                    <div>
                        <label class="block text-black font-medium mb-2">HSN/SAC Code</label>
                        <input type="text" name="hsn_code" v-model="form.hsn_code"
                            @keydown.enter.prevent="moveToNextInput"
                            class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                            placeholder="Enter HSN/SAC code" />
                    </div>
                    <div class="relative">
                        <label class="block text-black font-medium mb-2">Unit Type <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <input 
                                type="text" 
                                v-model="unitSearchQuery" 
                                @focus="showUnitDropdown = true"
                                @blur="closeUnitDropdownWithDelay"
                                @keydown.enter.prevent="moveToNextInput"
                                class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                                placeholder="Search & select unit..." 
                            />
                            <span class="absolute right-4 top-1/2 transform -translate-y-1/2 pointer-events-none text-gray-500">
                                <i class="bi bi-chevron-down"></i>
                            </span>
                        </div>

                        <!-- Dropdown List -->
                        <div 
                            v-if="showUnitDropdown" 
                            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto"
                        >
                            <div 
                                v-for="(label, key) in filteredUnitTypes" 
                                :key="key"
                                @mousedown="selectUnit(key, label)"
                                class="px-4 py-2.5 hover:bg-gray-100 cursor-pointer text-black transition-colors"
                            >
                                {{ label }}
                            </div>
                            <div v-if="Object.keys(filteredUnitTypes).length === 0" class="px-4 py-3 text-gray-500 text-sm text-center">
                                No units found
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Size and Alternate Unit Type Fields -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Width (e.g. for Plywood size)</label>
                    <input type="number" step="any" name="width" v-model="form.width"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="e.g. 8" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Height (e.g. for Plywood size)</label>
                    <input type="number" step="any" name="height" v-model="form.height"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="e.g. 4" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Alternate Unit Type</label>
                    <select name="alternate_unit_type" v-model="form.alternate_unit_type"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="">None</option>
                        <option v-for="(label, key) in unitTypes" :key="key" :value="key">
                            {{ label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-7">
                <div>
                    <label class="block text-black font-medium mb-2">Sale Price (₹)</label>
                    <input type="number" step="0.01" name="price" v-model="form.price"
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                        placeholder="Enter sale price" />
                </div>
                <div>
                    <label class="block text-black font-medium mb-2">Type <span class="text-red-500">*</span></label>
                    <select name="type" v-model="form.type" required
                        @keydown.enter.prevent="moveToNextInput"
                        class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition">
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                </div>
            </div>

            <div class="mt-7">
                <label class="block text-black font-medium mb-2">Description</label>
                <textarea name="description" v-model="form.description" rows="3"
                    @keydown.enter.prevent="moveToNextInput"
                    class="w-full px-4 py-3 bg-white text-black placeholder-gray-500 border border-gray-300 rounded-xl shadow-sm focus:ring-2 focus:ring-[#292688] focus:outline-none transition"
                    placeholder="Description"></textarea>
            </div>

                <div class="mt-7">
                    <label class="block text-black font-medium mb-2">Product Image</label>
                    <div 
                        @dragover="onDragOver" 
                        @dragleave="onDragLeave" 
                        @drop="onDrop"
                        :class="[
                            'border-2 border-dashed rounded-xl h-[180px] flex flex-col items-center justify-center p-4 text-center transition-all relative',
                            isDragging ? 'border-[#292688] bg-slate-50' : 'border-gray-300 hover:border-[#292688]'
                        ]"
                    >
                        <input 
                            type="file" 
                            @change="onFileChange" 
                            accept="image/*" 
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" 
                        />
                        
                        <div v-if="!imagePreview" class="space-y-2 pointer-events-none">
                            <i class="bi bi-cloud-arrow-up text-3xl text-gray-400"></i>
                            <p class="text-sm text-gray-600 font-medium">
                                Drag and drop image here, or <span class="text-[#292688] font-bold">browse</span>
                            </p>
                            <p class="text-xs text-gray-400">Supports JPG, PNG, GIF, WEBP (Max 2MB)</p>
                        </div>
                        
                        <div v-else class="w-full h-full flex items-center justify-between bg-slate-50 rounded-lg p-2 border border-gray-200 z-20">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <img :src="imagePreview" class="w-16 h-16 object-cover rounded-lg border border-gray-200" alt="Preview" />
                                <div class="text-left overflow-hidden">
                                    <p class="text-sm font-semibold text-gray-800 truncate" style="max-width: 180px;">
                                        {{ getImageName() }}
                                    </p>
                                </div>
                            </div>
                            <button 
                                @click.prevent="removeImage" 
                                type="button" 
                                class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-full transition cursor-pointer"
                            >
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition">Update</button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>

</template>