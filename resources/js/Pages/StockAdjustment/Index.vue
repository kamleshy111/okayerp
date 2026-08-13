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

const dtOptions = {
  lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
  pageLength: 10,
  order: [[1, 'desc']], // Order by Date descending
  responsive: true,
};

// Column definitions for DataTable
const columns = [
    { 
        data: null,
        title: 'S No',
        className: 'whitespace-nowrap',
        render: (data, type, row, meta) => meta.row + 1,
    },
    { data: 'date', title: 'Date', className: 'whitespace-nowrap' },
    { data: 'product_name', title: 'Product', className: 'whitespace-nowrap' },
    { data: 'sku', title: 'SKU', className: 'whitespace-nowrap' },
    { 
        data: 'type', 
        title: 'Type',
        className: 'whitespace-nowrap',
        render: (data) => {
            const badgeClass = data === 'Addition' ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200';
            return `<span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold border whitespace-nowrap ${badgeClass}">${data}</span>`;
        }
    },
    { 
        data: 'quantity', 
        title: 'Change Qty',
        className: 'whitespace-nowrap',
        render: (data, type, row) => {
            const prefix = row.type === 'Addition' ? '+' : '-';
            const colorClass = row.type === 'Addition' ? 'text-green-600 font-bold' : 'text-red-600 font-bold';
            return `<span class="${colorClass} whitespace-nowrap">${prefix}${data}</span>`;
        }
    },
    { 
        data: 'reason', 
        title: 'Reason / Remarks',
        className: 'whitespace-nowrap',
        render: (data, type, row) => {
            let content = `<strong class="whitespace-nowrap">${data}</strong>`;
            if (row.remarks) {
                content += `<div class="text-xs text-gray-500 mt-0.5 whitespace-nowrap">${row.remarks}</div>`;
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
      <div class="p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Stock Adjustments</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Track and manage inventory stock adjustments</p>
          </div>
          <div class="flex items-center gap-3">
            <a :href="route('stock-adjustment.create')"
                class="bg-[#2e2c92] hover:bg-[#201d70] text-white px-4 py-2.5 rounded-xl font-semibold shadow-sm transition flex items-center gap-2 text-sm">
                 <i class="fa fa-plus"></i>
                 <span>Add Adjustment</span>
            </a>
          </div>
        </div>

        <!-- Filter Panel -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col md:flex-row md:items-center gap-4">
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
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-6">
          <div class="overflow-x-auto">
            <DataTable :data="movements" :columns="columns" :options="dtOptions" id="stock-movement-table" class="w-full min-w-[850px]">
                <thead class="bg-[#2e2c92] text-white main-head-table">
                    <tr>
                        <th scope="col">S No</th>
                        <th scope="col">Date</th>
                        <th scope="col">Product</th>
                        <th scope="col">SKU</th>
                        <th scope="col">Type</th>
                        <th scope="col">Change Qty</th>
                        <th scope="col">Reason / Remarks</th>
                    </tr>
                </thead>
            </DataTable>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
</template>
