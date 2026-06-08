<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

// Profit & Loss data for chart
const profitLossData = computed(() => page.props.profitLossData ?? [])

const hoveredIndex = ref(null);
const tooltipX = ref(0);
const tooltipY = ref(0);

const handleMouseMove = (event, index) => {
    hoveredIndex.value = index;
    const rect = event.currentTarget.getBoundingClientRect();
    const parentRect = event.currentTarget.closest('.relative').getBoundingClientRect();
    tooltipX.value = event.clientX - parentRect.left + 15;
    tooltipY.value = event.clientY - parentRect.top - 10;
};

const handleMouseLeave = () => {
    hoveredIndex.value = null;
};

const svgWidth = 800;
const svgHeight = 350;
const padding = { top: 30, right: 30, bottom: 40, left: 60 };

const chartWidth = computed(() => svgWidth - padding.left - padding.right);
const chartHeight = computed(() => svgHeight - padding.top - padding.bottom);

// Math scaling
const scaleData = computed(() => {
    if (!profitLossData.value.length) return { max: 100, min: 0, scaleY: () => 0, zeroY: 0, stepX: 0 };
    
    const allValues = [];
    profitLossData.value.forEach(d => {
        allValues.push(d.sales, d.purchases, d.expenses, d.profit);
    });
    
    let max = Math.max(...allValues, 100);
    let min = Math.min(...allValues, 0);
    
    // Add 10% padding to max/min to avoid overflow
    const diff = max - min;
    max += diff * 0.1;
    if (min < 0) min -= diff * 0.1;
    
    if (max === min) {
        max = 100;
        min = 0;
    }
    
    const scaleY = (val) => {
        const pct = (val - min) / (max - min);
        return padding.top + chartHeight.value - (pct * chartHeight.value);
    };
    
    const zeroY = scaleY(0);
    const stepX = chartWidth.value / profitLossData.value.length;
    
    return { max, min, scaleY, zeroY, stepX };
});

// Grid lines
const gridLines = computed(() => {
    const { min, max, scaleY } = scaleData.value;
    const lines = [];
    const count = 5;
    for (let i = 0; i <= count; i++) {
        const val = min + (i * (max - min) / count);
        lines.push({
            value: val,
            y: scaleY(val),
            label: val.toLocaleString(undefined, { maximumFractionDigits: 0 })
        });
    }
    return lines;
});

// Profit line path
const profitLinePath = computed(() => {
    const { scaleY, stepX } = scaleData.value;
    if (!profitLossData.value.length) return '';
    
    return profitLossData.value.map((d, i) => {
        const x = padding.left + (i * stepX) + (stepX / 2);
        const y = scaleY(d.profit);
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');
});

// Profit dots coordinates
const profitDots = computed(() => {
    const { scaleY, stepX } = scaleData.value;
    return profitLossData.value.map((d, i) => {
        const x = padding.left + (i * stepX) + (stepX / 2);
        const y = scaleY(d.profit);
        return { x, y, value: d.profit, month: d.month };
    });
});

// Grouped bars coordinates
const bars = computed(() => {
    const { scaleY, zeroY, stepX } = scaleData.value;
    return profitLossData.value.map((d, i) => {
        const groupX = padding.left + i * stepX + (stepX * 0.15);
        const availWidth = stepX * 0.7;
        const barWidth = availWidth / 3.5;
        const gap = availWidth * 0.1;
        
        // Sales bar
        const salesHeight = Math.abs(zeroY - scaleY(d.sales));
        const salesY = d.sales >= 0 ? scaleY(d.sales) : zeroY;
        
        // Purchases bar
        const purchasesHeight = Math.abs(zeroY - scaleY(d.purchases));
        const purchasesY = d.purchases >= 0 ? scaleY(d.purchases) : zeroY;
        
        // Expenses bar
        const expensesHeight = Math.abs(zeroY - scaleY(d.expenses));
        const expensesY = d.expenses >= 0 ? scaleY(d.expenses) : zeroY;
        
        return {
            month: d.month,
            sales: {
                x: groupX,
                y: salesY,
                height: salesHeight,
                width: barWidth,
                value: d.sales
            },
            purchases: {
                x: groupX + barWidth + gap,
                y: purchasesY,
                height: purchasesHeight,
                width: barWidth,
                value: d.purchases
            },
            expenses: {
                x: groupX + 2 * (barWidth + gap),
                y: expensesY,
                height: expensesHeight,
                width: barWidth,
                value: d.expenses
            },
            centerX: padding.left + i * stepX + (stepX / 2),
            index: i
        };
    });
});
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

            <!-- Profit & Loss Chart -->
            <div class="bg-white shadow rounded-lg p-6 border border-gray-200 mt-8 hover:shadow-lg transition-shadow duration-300">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Financial Overview</h3>
                        <p class="text-sm text-gray-500">Sales, Purchases, Expenses & Net Profit (Last 6 Months)</p>
                    </div>
                    <!-- Legend -->
                    <div class="flex flex-wrap gap-4 text-xs font-semibold">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded bg-emerald-500 inline-block"></span>
                            <span class="text-gray-600">Sales</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded bg-rose-500 inline-block"></span>
                            <span class="text-gray-600">Purchases</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded bg-amber-500 inline-block"></span>
                            <span class="text-gray-600">Expenses</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-4 h-1 bg-blue-500 inline-block rounded"></span>
                            <span class="text-gray-600">Net Profit</span>
                        </div>
                    </div>
                </div>

                <div class="relative w-full h-[360px]">
                    <!-- If no data -->
                    <div v-if="profitLossData.length === 0" class="flex items-center justify-center h-full text-gray-400">
                        No financial data available for the last 6 months.
                    </div>
                    <div v-else class="w-full h-full">
                        <!-- Custom SVG Chart -->
                        <svg :viewBox="`0 0 ${svgWidth} ${svgHeight}`" class="w-full h-full" preserveAspectRatio="none">
                            <!-- Gradients definitions -->
                            <defs>
                                <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" />
                                    <stop offset="100%" stop-color="#059669" />
                                </linearGradient>
                                <linearGradient id="purchasesGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#f43f5e" />
                                    <stop offset="100%" stop-color="#e11d48" />
                                </linearGradient>
                                <linearGradient id="expensesGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#fbbf24" />
                                    <stop offset="100%" stop-color="#d97706" />
                                </linearGradient>
                                <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                                    <feGaussianBlur stdDeviation="3" result="blur" />
                                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                                </filter>
                            </defs>

                            <!-- Grid lines -->
                            <g class="grid-lines">
                                <line v-for="(line, idx) in gridLines" :key="idx" 
                                      :x1="padding.left" :y1="line.y" :x2="svgWidth - padding.right" :y2="line.y" 
                                      stroke="#f1f5f9" stroke-width="1.5" />
                            </g>

                            <!-- Y Axis Labels -->
                            <g class="y-labels text-[10px] fill-gray-400 font-semibold" text-anchor="end">
                                <text v-for="(line, idx) in gridLines" :key="idx" 
                                      :x="padding.left - 10" :y="line.y + 3">{{ line.label }}</text>
                            </g>

                            <!-- Zero Baseline -->
                            <line :x1="padding.left" :y1="scaleData.zeroY" :x2="svgWidth - padding.right" :y2="scaleData.zeroY" 
                                  stroke="#cbd5e1" stroke-width="1.5" stroke-dasharray="2 2" />

                            <!-- Bars -->
                            <g v-for="(bar, idx) in bars" :key="idx">
                                <!-- Sales bar -->
                                <rect :x="bar.sales.x" :y="bar.sales.y" :width="bar.sales.width" :height="bar.sales.height || 1" 
                                      fill="url(#salesGrad)" rx="2" class="transition-all duration-300 hover:opacity-90" />
                                <!-- Purchases bar -->
                                <rect :x="bar.purchases.x" :y="bar.purchases.y" :width="bar.purchases.width" :height="bar.purchases.height || 1" 
                                      fill="url(#purchasesGrad)" rx="2" class="transition-all duration-300 hover:opacity-90" />
                                <!-- Expenses bar -->
                                <rect :x="bar.expenses.x" :y="bar.expenses.y" :width="bar.expenses.width" :height="bar.expenses.height || 1" 
                                      fill="url(#expensesGrad)" rx="2" class="transition-all duration-300 hover:opacity-90" />
                            </g>

                            <!-- Profit Line -->
                            <path :d="profitLinePath" stroke="#3b82f6" stroke-width="3" fill="none" 
                                  filter="url(#glow)" stroke-linecap="round" stroke-linejoin="round" />

                            <!-- Profit Dots -->
                            <circle v-for="(dot, idx) in profitDots" :key="idx" 
                                    :cx="dot.x" :cy="dot.y" r="5" fill="#3b82f6" stroke="#ffffff" stroke-width="2" 
                                    class="transition-all duration-200" />

                            <!-- X Axis Month Labels -->
                            <g class="x-labels text-[10px] fill-gray-400 font-semibold" text-anchor="middle">
                                <text v-for="(bar, idx) in bars" :key="idx" 
                                      :x="bar.centerX" :y="svgHeight - 15">{{ bar.month }}</text>
                            </g>

                            <!-- Interactive hover areas covering each month column -->
                            <rect v-for="(bar, idx) in bars" :key="'hover-' + idx"
                                  :x="padding.left + idx * scaleData.stepX" :y="padding.top"
                                  :width="scaleData.stepX" :height="chartHeight"
                                  fill="transparent" class="cursor-pointer"
                                  @mousemove="handleMouseMove($event, idx)"
                                  @mouseleave="handleMouseLeave" />
                        </svg>

                        <!-- Tooltip box -->
                        <div v-if="hoveredIndex !== null" 
                             class="absolute z-10 bg-slate-900/95 text-white p-3 rounded-lg shadow-xl border border-slate-700/50 backdrop-blur-sm pointer-events-none transition-all duration-100 ease-out text-xs min-w-[160px]"
                             :style="{ left: `${tooltipX}px`, top: `${tooltipY}px` }">
                            <div class="font-bold text-gray-300 mb-1.5 border-b border-slate-700 pb-1">
                                {{ profitLossData[hoveredIndex].month }}
                            </div>
                            <div class="flex items-center justify-between gap-4 mb-1">
                                <span class="flex items-center gap-1 text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Sales:
                                </span>
                                <span class="font-mono font-bold text-emerald-400 text-right">
                                    ${{ parseFloat(profitLossData[hoveredIndex].sales).toFixed(2) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 mb-1">
                                <span class="flex items-center gap-1 text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Purchases:
                                </span>
                                <span class="font-mono font-bold text-rose-400 text-right">
                                    ${{ parseFloat(profitLossData[hoveredIndex].purchases).toFixed(2) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 mb-1.5">
                                <span class="flex items-center gap-1 text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span> Expenses:
                                </span>
                                <span class="font-mono font-bold text-amber-400 text-right">
                                    ${{ parseFloat(profitLossData[hoveredIndex].expenses).toFixed(2) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-slate-700 pt-1.5 font-semibold">
                                <span class="text-gray-300">Net Profit:</span>
                                <span :class="['font-mono text-right', profitLossData[hoveredIndex].profit >= 0 ? 'text-blue-400' : 'text-rose-400']">
                                    ${{ parseFloat(profitLossData[hoveredIndex].profit).toFixed(2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </AuthenticatedLayout>
</template>