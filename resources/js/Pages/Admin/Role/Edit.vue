<script setup>
import { ref, onMounted, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";
import axios from 'axios';

const props = defineProps({
  role: {
    type: Object,
    required: true
  },
  permissions: {
    type: Array,
    required: true
  },
  rolePermissions: {
    type: Array,
    required: true
  }
});

const form = ref({
  name: props.role.name,
  permissions: [...props.rolePermissions]
});

const isSystemRole = computed(() => {
  return ['admin', 'store'].includes(props.role.name.toLowerCase());
});

const nameInput = ref(null);

onMounted(() => {
  if (!isSystemRole.value) {
    nameInput.value?.focus();
  }
});

const submitForm = async () => {
  if (!form.value.name.trim()) {
    toast.error("Role Name is required!");
    return;
  }

  try {
    const response = await axios.post(`/role/update/${props.role.id}`, form.value);
    toast.success(response.data.message || "Role updated successfully!");
    
    // Redirect back to index after a short delay
    setTimeout(() => {
      router.get('/role');
    }, 1500);

  } catch (error) {
    const errorMessage =
      error.response?.data?.message ||
      (error.response?.data?.errors
        ? Object.values(error.response.data.errors).flat().join(", ")
        : "Failed to update role.");
    toast.error(errorMessage);
  }
};
</script>

<template>
  <Head title="Edit Role">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  </Head>

  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6 max-w-4xl mx-auto border border-gray-100">
      <div class="main-back-class">
        <a :href="route('role')" class="text-gray-500 hover:text-[#2e2c92] transition">
          <i style="font-size: 14px;" class="bi bi-chevron-left"></i>
          <span style="margin-left: 5px;">Back to Roles</span>
        </a>
      </div>
      
      <h2 class="text-2xl font-bold text-[#292688]">Edit Role</h2>

      <form @submit.prevent="submitForm" class="space-y-6">
        <div>
          <label class="block text-gray-700 font-semibold mb-2">Role Name</label>
          <input 
            ref="nameInput" 
            type="text" 
            v-model="form.name" 
            required
            :disabled="isSystemRole"
            class="w-full px-4 py-3 bg-white text-black placeholder-gray-400 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#292688] focus:outline-none transition shadow-sm disabled:bg-gray-100 disabled:text-gray-500"
            placeholder="e.g. Sales Manager" 
          />
          <p v-if="isSystemRole" class="text-xs text-gray-400 mt-1 italic">Note: Core system roles names cannot be renamed.</p>
        </div>

        <div>
          <label class="block text-gray-700 font-semibold mb-4">Assign Permissions</label>
          <div v-if="permissions.length === 0" class="text-gray-400 italic">
            No permissions available in the system. Create permissions first.
          </div>
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <label 
              v-for="permission in permissions" 
              :key="permission.id" 
              class="flex items-center gap-3 p-3 border border-gray-200 rounded-xl hover:bg-gray-50 transition cursor-pointer shadow-sm"
            >
              <input 
                type="checkbox" 
                :value="permission.name" 
                v-model="form.permissions"
                class="rounded border-gray-300 text-[#292688] focus:ring-[#292688] h-5 w-5"
              />
              <span class="text-sm font-medium capitalize text-gray-700">{{ permission.name }}</span>
            </label>
          </div>
        </div>

        <div class="pt-4 border-t border-gray-100 flex gap-4">
          <button 
            type="submit" 
            class="bg-[#2e2c92] text-white font-semibold px-6 py-3 rounded-xl hover:bg-[#2e2c92e6] transition shadow-md"
          >
            Update Role
          </button>
          <a 
            :href="route('role')" 
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-6 py-3 rounded-xl transition text-center"
          >
            Cancel
          </a>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
