<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import vSelect from "vue3-select";
import "vue3-select/dist/vue3-select.css";

const props = defineProps({
  movements: {
    type: Array,
    required: true
  },
  products: {
    type: Array,
    required: true
  },
  selectedProductId: {
    type: [String, Number],
    default: ''
  }
});

const filterProductId = ref(props.selectedProductId ? parseInt(props.selectedProductId) : null);

// Column definitions for DataTable
const columns = [
    { 
        data: null,
        title: 'S No',
        render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'date', title: 'Date' },
    { data: 'product_name', title: 'Product' },
    { data: 'sku', title: 'SKU' },
    { 
        data: 'type', 
        title: 'Type',
        render: (data) => {
            const badgeClass = data === 'Addition' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200';
            return `<span class="px-2.5 py-1 rounded-full text-xs font-semibold border ${badgeClass}">${data}</span>`;
        }
    },
    { 
        data: 'quantity', 
        title: 'Change Qty',
        render: (data, type, row) => {
            const prefix = row.type === 'Addition' ? '+' : '-';
            const colorClass = row.type === 'Addition' ? 'text-green-600 font-bold' : 'text-red-600 font-bold';
            return `<span class="${colorClass}">${prefix}${data}</span>`;
        }
    },
    { 
        data: 'reference_type', 
        title: 'Source',
        render: (data) => {
            let badgeClass = 'bg-gray-100 text-gray-800 border-gray-200';
            if (data === 'Manual') badgeClass = 'bg-blue-100 text-blue-800 border-blue-200';
            else if (data === 'Sale') badgeClass = 'bg-purple-100 text-purple-800 border-purple-200';
            else if (data === 'Purchase') badgeClass = 'bg-teal-100 text-teal-800 border-teal-200';
            return `<span class="px-2.5 py-1 rounded-full text-xs font-semibold border ${badgeClass}">${data}</span>`;
        }
    },
    { 
        data: 'reason', 
        title: 'Reason / Remarks',
        render: (data, type, row) => {
            let content = `<strong>${data}</strong>`;
            if (row.remarks) {
                content += `<div class="text-xs text-gray-500 mt-0.5">${row.remarks}</div>`;
            }
            return content;
        }
    }
];

watch(filterProductId, (newVal) => {
    router.get(route('stock-adjustment.index'), { product_id: newVal || '' }, { preserveState: true });
});
</script>

<template>
    <Head title="Stock Adjustments">
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    </Head>  

    <AuthenticatedLayout>
      <div class="p-6">
        <div class="flex items-center justify-between mb-6">
          <h1 class="text-3xl font-bold text-[#2e2c92]">Stock Adjustments</h1>
          <div class="flex items-center gap-4">
            <a :href="route('stock-adjustment.create')" class="hover:bg-[#2e2c92] hover:text-white border border-[#2e2c92] text-[#2e2c92] px-4 py-2 rounded-lg font-medium transition duration-200">
                <span>➕ Add</span>
            </a>
          </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 flex flex-col md:flex-row md:items-center gap-4">
            <div class="w-full md:w-80">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Filter by Product</label>
                <vSelect
                    v-model="filterProductId"
                    :options="products"
                    label="name"
                    :reduce="product => product.id"
                    placeholder="All Products"
                    class="w-full text-black bg-white"
                />
            </div>
            <div v-if="filterProductId" class="mt-4 md:mt-auto">
                <button @click="filterProductId = null" class="text-xs font-semibold text-[#2e2c92] hover:underline">
                    Clear Filter
                </button>
            </div>
        </div>

        <!-- DataTable -->
        <div class="overflow-x-auto mt-6 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
          <DataTable :data="movements" :columns="columns" id="stock-movement-table">
              <thead class="bg-[#2e2c92] text-white main-head-table">
                  <tr>
                      <th scope="col">S No</th>
                      <th scope="col">Date</th>
                      <th scope="col">Product</th>
                      <th scope="col">SKU</th>
                      <th scope="col">Type</th>
                      <th scope="col">Change Qty</th>
                      <th scope="col">Source</th>
                      <th scope="col">Reason / Remarks</th>
                  </tr>
              </thead>
            </DataTable>
        </div>
      </div>
    </AuthenticatedLayout>
</template>
