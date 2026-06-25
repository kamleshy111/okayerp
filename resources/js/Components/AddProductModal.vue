<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { toast } from "vue3-toastify";

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  initialName: {
    type: String,
    default: '',
  },
  categories: {
    type: Array,
    default: () => [],
  },
  unitTypes: {
    type: Object,
    default: () => ({}),
  },
});

const emit = defineEmits(['close', 'success']);

const imagePreview = ref(null);
const isDragging = ref(false);

const form = ref({
  name: '',
  category_id: '',
  unit_type: '',
  sgst: 0,
  cgst: 0,
  hsn_code: '',
  price: 0,
  description: '',
  image: null,
});

// Watch for initialName or visibility changes to reset form
watch(
  () => props.show,
  (newVal) => {
    if (newVal) {
      form.value = {
        name: props.initialName || '',
        category_id: '',
        unit_type: '',
        sgst: 0,
        cgst: 0,
        hsn_code: '',
        price: 0,
        description: '',
        image: null,
      };
      imagePreview.value = null;
    }
  }
);

const handleFileSelect = (file) => {
  if (file && file.type.startsWith('image/')) {
    form.value.image = file;
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
  imagePreview.value = null;
};

const submitProduct = async () => {
  try {
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

    const formData = new FormData();
    for (const [key, value] of Object.entries(form.value)) {
      if (value !== null && value !== undefined) {
        formData.append(key, value);
      }
    }

    const response = await axios.post('/product/store', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    const createdProduct = response.data;

    toast.success("Product added successfully!");
    emit('success', createdProduct);
  } catch (error) {
    const errorMessage = error.response?.data?.message || "An error occurred. Please try again.";
    toast.error(errorMessage);
  }
};
</script>

<template>
  <div v-if="show"
       class="fixed inset-0 overflow-y-auto bg-black/50 backdrop-blur-sm transition-all duration-300 flex items-start sm:items-center justify-center p-4 sm:p-6"
       style="z-index: 99999;"
       @click.self="emit('close')">
    <div class="bg-white p-6 rounded-2xl shadow-2xl w-full max-w-lg my-auto transform transition-all duration-300 border border-gray-100 space-y-4">
      <div class="flex justify-between items-center pb-2 border-b border-gray-100">
        <h2 class="text-xl font-bold text-[#292688]">Add New Product</h2>
        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition">
          <i class="fa fa-close"></i>
        </button>
      </div>

      <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1 text-black">
        <div>
          <label class="block text-sm font-medium text-gray-750 mb-1">Product Name <span class="text-red-500">*</span></label>
          <input type="text" v-model="form.name" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-750 mb-1">Category <span class="text-red-500">*</span></label>
            <select v-model="form.category_id" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white">
              <option value="">Select Category</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-750 mb-1">Unit Type <span class="text-red-500">*</span></label>
            <select v-model="form.unit_type" required class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none bg-white">
              <option value="">Select Unit</option>
              <option v-for="(label, key) in unitTypes" :key="key" :value="key">
                {{ label }}
              </option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-750 mb-1">HSN/SAC Code</label>
            <input type="text" v-model="form.hsn_code" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-750 mb-1">Price</label>
            <input type="number" step="0.01" v-model="form.price" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-750 mb-1">Description</label>
          <textarea v-model="form.description" class="w-full border border-gray-300 px-3 py-2 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none" rows="2"></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-750 mb-1">Product Image</label>
          <div 
              @dragover="onDragOver" 
              @dragleave="onDragLeave" 
              @drop="onDrop"
              :class="[
                  'border-2 border-dashed rounded-xl h-[140px] flex flex-col items-center justify-center p-4 text-center transition-all relative',
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
                      Drag & drop image, or <span class="text-[#292688] font-bold">browse</span>
                  </p>
                  <p class="text-xs text-gray-400">JPG, PNG, GIF, WEBP (Max 2MB)</p>
              </div>
              
              <div v-else class="w-full h-full flex items-center justify-between bg-slate-50 rounded-lg p-2 border border-gray-200 z-20">
                  <div class="flex items-center gap-3 overflow-hidden">
                      <img :src="imagePreview" class="w-12 h-12 object-cover rounded-lg border border-gray-200" alt="Preview" />
                      <div class="text-left overflow-hidden">
                          <p class="text-xs font-semibold text-gray-800 truncate" style="max-width: 180px;">
                              {{ form.image ? form.image.name : 'Image Selected' }}
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
      </div>

      <div class="mt-6 flex justify-end gap-3 pt-2">
        <button
          @click="emit('close')"
          class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl transition cursor-pointer font-medium"
        >
          Cancel
        </button>
        <button
          @click="submitProduct"
          class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-md transition cursor-pointer font-medium"
        >
          Save Product
        </button>
      </div>
    </div>
  </div>
</template>
