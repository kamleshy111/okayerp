<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';

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
const totalCustomerDue = computed(() => page.props.totalCustomerDue ?? 0)
const totalSupplierDue = computed(() => page.props.totalSupplierDue ?? 0)

// Profit & Loss data for chart
const profitLossData = computed(() => page.props.profitLossData ?? [])

const hoveredIndex = ref(null);
const tooltipX = ref(0);
const tooltipY = ref(0);

const selectedMonths = ref(6);

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('months')) {
        selectedMonths.value = parseInt(urlParams.get('months'));
    }
});

const updateMonths = () => {
    router.get(route('dashboard'), { months: selectedMonths.value }, { preserveState: true, preserveScroll: true });
};

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
        allValues.push(d.sales, d.purchases, d.expenses, d.incomes || 0, d.profit);
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
        const groupX = padding.left + i * stepX + (stepX * 0.1);
        const availWidth = stepX * 0.8;
        const barWidth = availWidth / 4.45;
        const gap = barWidth * 0.15;
        
        // Sales bar
        const salesHeight = Math.abs(zeroY - scaleY(d.sales));
        const salesY = d.sales >= 0 ? scaleY(d.sales) : zeroY;
        
        // Purchases bar
        const purchasesHeight = Math.abs(zeroY - scaleY(d.purchases));
        const purchasesY = d.purchases >= 0 ? scaleY(d.purchases) : zeroY;
        
        // Expenses bar
        const expensesHeight = Math.abs(zeroY - scaleY(d.expenses));
        const expensesY = d.expenses >= 0 ? scaleY(d.expenses) : zeroY;

        // Incomes bar
        const incomesHeight = Math.abs(zeroY - scaleY(d.incomes || 0));
        const incomesY = (d.incomes || 0) >= 0 ? scaleY(d.incomes || 0) : zeroY;
        
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
            incomes: {
                x: groupX + 3 * (barWidth + gap),
                y: incomesY,
                height: incomesHeight,
                width: barWidth,
                value: d.incomes || 0
            },
            centerX: padding.left + i * stepX + (stepX / 2),
            index: i
        };
    });
});
</script>

<template>

    <Head title="Dashboard">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </Head>

    <AuthenticatedLayout>

        <section class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">

                <!-- Card 1: Product Overview -->
                <div class="bg-white shadow rounded-xl p-5 border-l-4 border-indigo-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Catalog</p>
                            <p class="text-xs text-gray-500 font-medium">Total Products</p>
                            <p class="text-2xl font-black text-gray-800 mt-1">{{ totalProducts }}</p>
                        </div>
                        <div class="bg-indigo-50 text-indigo-600 p-3 rounded-xl">
                            <i class="bi bi-box-seam text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-400">
                            Growth: 
                            <span :class="['font-bold', percentageChangeProduct >= 0 ? 'text-emerald-500' : 'text-rose-500']">
                                {{ percentageChangeProduct >= 0 ? '▲' : '▼' }} {{ Math.abs(percentageChangeProduct) }}%
                            </span>
                        </span>
                        <a :href="route('product')" class="text-xs text-indigo-600 font-bold hover:underline">Manage Products →</a>
                    </div>
                </div>

                <!-- Card 2: Total Categories -->
                <div class="bg-white shadow rounded-xl p-5 border-l-4 border-purple-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Grouping</p>
                            <p class="text-xs text-gray-500 font-medium">Total Categories</p>
                            <p class="text-2xl font-black text-gray-800 mt-1">{{ totalCategories }}</p>
                        </div>
                        <div class="bg-purple-50 text-purple-600 p-3 rounded-xl">
                            <i class="bi bi-tags text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-400">Inventory groupings</span>
                        <a :href="route('category')" class="text-xs text-purple-600 font-bold hover:underline">Manage Categories →</a>
                    </div>
                </div>

                <!-- Card 3: Total Customers -->
                <div class="bg-white shadow rounded-xl p-5 border-l-4 border-emerald-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Directory</p>
                            <p class="text-xs text-gray-500 font-medium">Total Customers</p>
                            <p class="text-2xl font-black text-gray-800 mt-1">{{ totalCustomers }}</p>
                        </div>
                        <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl">
                            <i class="bi bi-people text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-400">
                            Growth: 
                            <span :class="['font-bold', percentageChangeCustomer >= 0 ? 'text-emerald-500' : 'text-rose-500']">
                                {{ percentageChangeCustomer >= 0 ? '▲' : '▼' }} {{ Math.abs(percentageChangeCustomer) }}%
                            </span>
                        </span>
                        <a :href="route('customer')" class="text-xs text-emerald-600 font-bold hover:underline">Manage Customers →</a>
                    </div>
                </div>

                <!-- Card 4: Total Suppliers -->
                <div class="bg-white shadow rounded-xl p-5 border-l-4 border-teal-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Directory</p>
                            <p class="text-xs text-gray-500 font-medium">Total Suppliers</p>
                            <p class="text-2xl font-black text-gray-800 mt-1">{{ totalSuppliers }}</p>
                        </div>
                        <div class="bg-teal-50 text-teal-600 p-3 rounded-xl">
                            <i class="bi bi-truck text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-400">
                            Growth: 
                            <span :class="['font-bold', percentageChangeSupplier >= 0 ? 'text-emerald-500' : 'text-rose-500']">
                                {{ percentageChangeSupplier >= 0 ? '▲' : '▼' }} {{ Math.abs(percentageChangeSupplier) }}%
                            </span>
                        </span>
                        <a :href="route('supplier')" class="text-xs text-teal-600 font-bold hover:underline">Manage Suppliers →</a>
                    </div>
                </div>

                <!-- Card 5: Customer Outstanding Due -->
                <div class="bg-rose-50/30 shadow rounded-xl p-5 border-l-4 border-rose-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-rose-500 uppercase tracking-wider mb-1">Receivables</p>
                            <p class="text-xs text-gray-500 font-medium">Customer Dues</p>
                            <p class="text-2xl font-black text-rose-600 font-mono mt-1">₹ {{ parseFloat(totalCustomerDue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                        </div>
                        <div class="bg-rose-100/60 text-rose-600 p-3 rounded-xl">
                            <i class="bi bi-cash-coin text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-rose-100/30 pt-3">
                        <span class="text-xs text-gray-400">Unpaid sales invoices</span>
                        <a :href="route('sale')" class="text-xs text-rose-600 font-bold hover:underline">View Sales →</a>
                    </div>
                </div>

                <!-- Card 6: Supplier Outstanding Due -->
                <div class="bg-amber-50/30 shadow rounded-xl p-5 border-l-4 border-amber-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider mb-1">Payables</p>
                            <p class="text-xs text-gray-500 font-medium">Supplier Dues</p>
                            <p class="text-2xl font-black text-amber-600 font-mono mt-1">₹ {{ parseFloat(totalSupplierDue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                        </div>
                        <div class="bg-amber-100/60 text-amber-600 p-3 rounded-xl">
                            <i class="bi bi-wallet2 text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-amber-100/30 pt-3">
                        <span class="text-xs text-gray-400">Unpaid purchase bills</span>
                        <a :href="route('purchase')" class="text-xs text-amber-600 font-bold hover:underline">View Purchases →</a>
                    </div>
                </div>

                <!-- Card 7: Products In Stock -->
                <div class="bg-white shadow rounded-xl p-5 border-l-4 border-cyan-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Inventory</p>
                            <p class="text-xs text-gray-500 font-medium">Items In Stock</p>
                            <p class="text-2xl font-black text-gray-800 mt-1">{{ totalStockProducts }}</p>
                        </div>
                        <div class="bg-cyan-50 text-cyan-600 p-3 rounded-xl">
                            <i class="bi bi-archive text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-400">
                            Purchases: 
                            <span :class="['font-bold', percentageChangePurchases >= 0 ? 'text-emerald-500' : 'text-rose-500']">
                                {{ percentageChangePurchases >= 0 ? '▲' : '▼' }} {{ Math.abs(percentageChangePurchases) }}%
                            </span>
                        </span>
                        <a :href="route('purchase')" class="text-xs text-cyan-600 font-bold hover:underline">View Inventory →</a>
                    </div>
                </div>

                <!-- Card 8: Total Items Sold -->
                <div class="bg-white shadow rounded-xl p-5 border-l-4 border-slate-500 hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Performance</p>
                            <p class="text-xs text-gray-500 font-medium">Total Items Sold</p>
                            <p class="text-2xl font-black text-gray-800 mt-1">{{ totalSaleProducts }}</p>
                        </div>
                        <div class="bg-slate-50 text-slate-600 p-3 rounded-xl">
                            <i class="bi bi-cart-check text-2xl"></i>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 border-t border-gray-100 pt-3">
                        <span class="text-xs text-gray-400">
                            Sales: 
                            <span :class="['font-bold', percentageChangeSale >= 0 ? 'text-emerald-500' : 'text-rose-500']">
                                {{ percentageChangeSale >= 0 ? '▲' : '▼' }} {{ Math.abs(percentageChangeSale) }}%
                            </span>
                        </span>
                        <a :href="route('sale')" class="text-xs text-slate-600 font-bold hover:underline">View Sales →</a>
                    </div>
                </div>

            </div>

            <!-- Profit & Loss Chart -->
            <div class="bg-white shadow rounded-lg p-6 border border-gray-200 mt-8 hover:shadow-lg transition-shadow duration-300">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Financial Overview</h3>
                        <p class="text-sm text-gray-500">Sales, Purchases, Expenses & Net Profit (Last {{ selectedMonths }} Months)</p>
                    </div>
                    <!-- Controls and Legend -->
                    <div class="flex flex-col items-end gap-3">
                        <select v-model="selectedMonths" @change="updateMonths" class="text-sm border-gray-200 text-gray-600 rounded-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-3">
                            <option value="3">Last 3 Months</option>
                            <option value="6">Last 6 Months</option>
                            <option value="9">Last 9 Months</option>
                            <option value="12">Last 12 Months</option>
                        </select>
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
                            <span class="w-3.5 h-3.5 rounded bg-purple-500 inline-block"></span>
                            <span class="text-gray-600">Incomes</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-4 h-1 bg-blue-500 inline-block rounded"></span>
                            <span class="text-gray-600">Net Profit</span>
                        </div>
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
                                <linearGradient id="incomesGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#a855f7" />
                                    <stop offset="100%" stop-color="#7e22ce" />
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
                                <!-- Incomes bar -->
                                <rect :x="bar.incomes.x" :y="bar.incomes.y" :width="bar.incomes.width" :height="bar.incomes.height || 1" 
                                      fill="url(#incomesGrad)" rx="2" class="transition-all duration-300 hover:opacity-90" />
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
                                    ₹{{ parseFloat(profitLossData[hoveredIndex].sales).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 mb-1">
                                <span class="flex items-center gap-1 text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Purchases:
                                </span>
                                <span class="font-mono font-bold text-rose-400 text-right">
                                    ₹{{ parseFloat(profitLossData[hoveredIndex].purchases).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 mb-1">
                                <span class="flex items-center gap-1 text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span> Expenses:
                                </span>
                                <span class="font-mono font-bold text-amber-400 text-right">
                                    ₹{{ parseFloat(profitLossData[hoveredIndex].expenses).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 mb-1.5">
                                <span class="flex items-center gap-1 text-gray-400">
                                    <span class="w-2 h-2 rounded-full bg-purple-500"></span> Incomes:
                                </span>
                                <span class="font-mono font-bold text-purple-400 text-right">
                                    ₹{{ parseFloat(profitLossData[hoveredIndex].incomes || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-slate-700 pt-1.5 font-semibold">
                                <span class="text-gray-300">Net Profit:</span>
                                <span :class="['font-mono text-right', profitLossData[hoveredIndex].profit >= 0 ? 'text-blue-400' : 'text-rose-400']">
                                    ₹{{ parseFloat(profitLossData[hoveredIndex].profit).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>
    </AuthenticatedLayout>
</template>