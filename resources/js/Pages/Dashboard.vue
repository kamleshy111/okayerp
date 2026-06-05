<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage()

// Props from backend (DashboardController)
const role = computed(() => page.props.role)
const totalCustomers = computed(() => page.props.totalCustomers ?? 0)
const totalSuppliers = computed(() => page.props.totalSuppliers ?? 0)
const totalCategories = computed(() => page.props.totalCategories ?? 0)
const totalProducts = computed(() => page.props.totalProducts ?? 0)
const totalStockProducts = computed(() => page.props.totalStockProducts ?? 0)
const totalSaleProducts = computed(() => page.props.totalSaleProducts)
const percentageChangeSale = computed(() => page.props.percentageChangeSale ?? 0)
const percentageChangeProduct = computed(() => page.props.percentageChangeProduct ?? 0)
const percentageChangeCustomer = computed(() => page.props.percentageChangeCustomer ?? 0)
const percentageChangeSupplier = computed(() => page.props.percentageChangeSupplier ?? 0)
const percentageChangePurchases = computed(() => page.props.percentageChangePurchases ?? 0)
</script>

<template>

    <Head title="Dashboard" />

    <AuthenticatedLayout>

        <section class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">


                <!-- Total Products -->
                <div
                    class="bg-white shadow rounded-lg p-4 border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                    <p class="font-semibold text-gray-400 mb-2 uppercase">Product Overview</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <span class="text-blue-500 text-xl p-0">📦</span>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 text-sm">Total Products</p>
                            <p class="text-lg font-bold text-gray-800">{{ totalProducts }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-green-500 mb-2">
                        <span
                            :class="{
                                'text-green-500': percentageChangeProduct > 0,
                                'text-red-500': percentageChangeProduct < 0,
                                'text-gray-500': percentageChangeProduct === 0
                            }"
                        >
                            {{ percentageChangeProduct > 0 ? '▲' : percentageChangeProduct < 0 ? '▼' : '–' }}
                            {{ percentageChangeProduct }}%
                        </span>

                        <span class="text-gray-500">Since Last Month</span>
                    </div>
                    <a :href="route('product')" class="text-sm text-blue-500 font-medium">Manage Products</a>
                </div>

                <!-- Total Categories -->
                <div
                    class="bg-white shadow rounded-lg p-4 border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                    <p class="font-semibold text-gray-400 mb-2 uppercase">Categories Overview</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <span class="text-blue-500 text-xl p-0">🗂️</span>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 text-sm">Total Categories</p>
                            <p class="text-lg font-bold text-gray-800">{{ totalCategories }}</p>
                        </div>
                    </div>
                    <a :href="route('category')" class="text-sm text-blue-500 font-medium">Manage Categories</a>
                </div>

                <!-- Total Customers -->
                <div
                    class="bg-white shadow rounded-lg p-4 border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                    <p class="font-semibold text-gray-400 mb-2 uppercase">Customers Overview</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <span class="text-blue-500 text-xl p-0">👥</span>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 text-sm">Total Customers</p>
                            <p class="text-lg font-bold text-gray-800">{{ totalCustomers }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-green-500 mb-2">
                        <span
                            :class="{
                                'text-green-500': percentageChangeCustomer > 0,
                                'text-red-500': percentageChangeCustomer < 0,
                                'text-gray-500': percentageChangeCustomer === 0
                            }"
                        >
                            {{ percentageChangeCustomer > 0 ? '▲' : percentageChangeCustomer < 0 ? '▼' : '–' }}
                            {{ percentageChangeCustomer }}%
                        </span>

                        <span class="text-gray-500">Since Last Month</span>
                    </div>
                    <a :href="route('customer')" class="text-sm text-blue-500 font-medium">Manage Customers</a>
                </div>
                
                <!-- Total Suppliers -->
                <div
                    class="bg-white shadow rounded-lg p-4 border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                    <p class="font-semibold text-gray-400 mb-2 uppercase">Suppliers Overview</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-blue-100 p-2 rounded-full">
                            <span class="text-blue-500 text-xl p-0">👥</span>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 text-sm">Total Suppliers</p>
                            <p class="text-lg font-bold text-gray-800">{{ totalSuppliers }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-green-500 mb-2">
                        <span
                            :class="{
                                'text-green-500': percentageChangeSupplier > 0,
                                'text-red-500': percentageChangeSupplier < 0,
                                'text-gray-500': percentageChangeSupplier === 0
                            }"
                        >
                            {{ percentageChangeSupplier > 0 ? '▲' : percentageChangeSupplier < 0 ? '▼' : '–' }}
                            {{ percentageChangeSupplier }}%
                        </span>

                        <span class="text-gray-500">Since Last Month</span>
                    </div>
                    <a :href="route('supplier')" class="text-sm text-blue-500 font-medium">Manage Suppliers</a>
                </div>

                <!-- Products In Stock -->
                <div
                    class="bg-white shadow rounded-lg p-4 border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                    <p class="font-semibold text-gray-400 mb-2 uppercase">Inventory Status</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-green-100 p-2 rounded-full">
                            <span class="text-green-500 text-xl p-0">✅</span>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 text-sm">Products in Stock</p>
                            <p class="text-lg font-bold text-gray-800">{{ totalStockProducts }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-green-500 mb-2">
                        <span
                            :class="{
                                'text-green-500': percentageChangePurchases > 0,
                                'text-red-500': percentageChangePurchases < 0,
                                'text-gray-500': percentageChangePurchases === 0
                            }"
                        >
                            {{ percentageChangePurchases > 0 ? '▲' : percentageChangePurchases < 0 ? '▼' : '–' }}
                            {{ percentageChangePurchases }}%
                        </span>
                        <span class="text-gray-500">Since Last Month</span></div>
                    <a :href="route('purchase')" class="text-sm text-blue-500 font-medium">View Inventory</a>
                </div>

                <!-- Products In Sales -->
                <div
                    class="bg-white shadow rounded-lg p-4 border border-gray-200 hover:shadow-lg transition-shadow duration-300">
                    <p class="font-semibold text-gray-400 mb-2 uppercase">Inventory Status</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="bg-green-100 p-2 rounded-full">
                            <span class="text-green-500 text-xl p-0">✅</span>
                        </div>
                        <div class="text-right">
                            <p class="text-gray-500 text-sm">Products in Sale</p>
                            <p class="text-lg font-bold text-gray-800">{{ totalSaleProducts }}</p>
                        </div>
                    </div>
                    <div class="text-sm text-green-500 mb-2">
                        <span
                            :class="{
                                'text-green-500': percentageChangeSale > 0,
                                'text-red-500': percentageChangeSale < 0,
                                'text-gray-500': percentageChangeSale === 0
                            }"
                        >
                            {{ percentageChangeSale > 0 ? '▲' : percentageChangeSale < 0 ? '▼' : '–' }}
                            {{ percentageChangeSale }}%
                        </span>

                        <span class="text-gray-500">Since Last Month</span>
                    </div>
                    <a :href="route('sale')" class="text-sm text-blue-500 font-medium">View Inventory</a>
                </div>

            </div>
        </section>
    </AuthenticatedLayout>
</template>