<script setup>
import { ref, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";

const activeItem = ref(route().current());
const page = usePage();

// Props
defineProps({
  role: {
    type: String,
    required: true,
  },
});

// Emits
const emit = defineEmits(['close-sidebar']);

const hasPermission = (permission) => {
  return page.props.auth.user?.permissions?.includes(permission);
};

onMounted(() => {
  console.log("Sidebar Auth User Permissions:", page.props.auth.user?.permissions);
});
</script>

<template>
  <!-- Sidebar -->
  <div class="flex flex-col h-full">
    <!-- Sidebar Header (Logo/Title & Close Button for mobile) -->
    <div class="flex items-center justify-between px-4 h-16 border-b border-indigo-700/50">
        <span class="text-xl font-black tracking-wider text-white">
            <a :href="route('dashboard')" class="flex items-center gap-3 px-4 py-2 rounded-l-full"> OkayERP </a>
        </span>
        <!-- Close button visible only on mobile -->
        <button @click="emit('close-sidebar')"
            class="text-indigo-200 hover:text-white md:hidden focus:outline-none"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto no-scrollbar">
      <ul class="space-y-1.5 pl-3 mt-4 pb-6">

        <!-- Dashboard -->
        <li :class="{ 'active': route().current('dashboard') }">
          <a
            :href="route('dashboard')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-speedometer2 text-xl"></i> <span>Dashboard</span>
          </a>
        </li>

        <!-- Section: Sales & Customers (Daily Front-office Operations) -->
        <li v-if="role === 'store' && (hasPermission('customer manage') || hasPermission('sale manage') || hasPermission('payments customer manage'))" class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Sales & Customers
        </li>

        <li v-if="role === 'store' && hasPermission('sale manage')" :class="{ 'active': route().current('sale*') && !route().current('sale-return*') }">
          <a
            :href="route('sale')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-cart3 text-xl"></i> <span>Sales</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('sale manage')" :class="{ 'active': route().current('estimate*') }">
          <a
            :href="route('estimate.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-file-earmark-text text-xl"></i> <span>Estimates</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('sale manage')" :class="{ 'active': route().current('sale-return*') }">
          <a
            :href="route('sale-return.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-arrow-counterclockwise text-xl"></i> <span>Sales Returns</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('payments customer manage')" :class="{ 'active': route().current('paymentsCustomer*') && !route().current('paymentsCustomer.history') }">
          <a
            :href="route('paymentsCustomer')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-wallet2 text-xl"></i> <span>Customers Payments</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('customer manage')" :class="{ 'active': route().current('customer*') || route().current('paymentsCustomer.history') }">
          <a
            :href="route('customer')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-people text-xl"></i> <span>Customers</span>
          </a>
        </li>

        <!-- Section: Catalog & Stock (Supporting Catalog & Inventory) -->
        <li v-if="role === 'store' && (hasPermission('product manage') || hasPermission('category manage'))" class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Inventory & Catalog
        </li>

        <li v-if="role === 'store' && hasPermission('product manage')" :class="{ 'active': route().current('product*') }">
          <a
            :href="route('product')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-box-seam text-xl"></i> <span>Products</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('category manage')" :class="{ 'active': route().current('category*') }">
          <a
            :href="route('category')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-tags text-xl"></i> <span>Categories</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('product manage')" :class="{ 'active': route().current('stock-adjustment*') }">
          <a
            :href="route('stock-adjustment.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-sliders text-xl"></i> <span>Stock Adjustments</span>
          </a>
        </li>

        <!-- Section: Purchases & Suppliers (Back-office Operations) -->
        <li v-if="role === 'store' && (hasPermission('supplier manage') || hasPermission('purchase manage') || hasPermission('payment supplier manage'))" class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Purchases & Suppliers
        </li>

        <li v-if="role === 'store' && hasPermission('purchase manage')" :class="{ 'active': route().current('purchase*') && !route().current('purchase-return*') }">
          <a
            :href="route('purchase')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-bag-plus text-xl"></i> <span>Purchase</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('purchase manage')" :class="{ 'active': route().current('purchase-return*') }">
          <a
            :href="route('purchase-return.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-arrow-clockwise text-xl"></i> <span>Purchase Returns</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('payment supplier manage')" :class="{ 'active': route().current('paymentSupplier*') && !route().current('paymentSupplier.history') }">
          <a
            :href="route('paymentSupplier')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-credit-card text-xl"></i> <span>Suppliers Payments</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('supplier manage')" :class="{ 'active': route().current('supplier*') || route().current('paymentSupplier.history') }">
          <a
            :href="route('supplier')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-truck text-xl"></i> <span>Suppliers</span>
          </a>
        </li>

        <!-- Section: Incomes (Inflows) -->
        <li v-if="role === 'store' && (hasPermission('income manage') || hasPermission('income category manage'))" class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Incomes
        </li>

        <li v-if="role === 'store' && hasPermission('income manage')" :class="{ 'active': route().current('income*') && !route().current('income-category*') }">
          <a
            :href="route('income')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-cash text-xl"></i> <span>Incomes</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('income category manage')" :class="{ 'active': route().current('income-category*') }">
          <a
            :href="route('income-category')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-collection text-xl"></i> <span>Income Categories</span>
          </a>
        </li>

        <!-- Section: Expenses (Outflows) -->
        <li v-if="role === 'store' && (hasPermission('expense manage') || hasPermission('expense category manage'))" class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Expenses
        </li>

        <li v-if="role === 'store' && hasPermission('expense manage')" :class="{ 'active': route().current('expense*') && !route().current('expense-category*') }">
          <a
            :href="route('expense')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-receipt text-xl"></i> <span>Expenses</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('expense category manage')" :class="{ 'active': route().current('expense-category*') }">
          <a
            :href="route('expense-category')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-collection text-xl"></i> <span>Expense Categories</span>
          </a>
        </li>

        <!-- Section: Reports & Accounting (Periodic/Strategic Review) -->
        <li v-if="role === 'store'" class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Reports & Accounting
        </li>

        <li v-if="role === 'store' && (hasPermission('payments customer manage') || hasPermission('payment supplier manage'))" :class="{ 'active': route().current('reports.aging') }">
          <a
            :href="route('reports.aging')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-calendar3-range text-xl"></i> <span>AR/AP Aging</span>
          </a>
        </li>

        <li v-if="role === 'store'" :class="{ 'active': route().current('reports.ledger') }">
          <a
            :href="route('reports.ledger')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-journal-text text-xl"></i> <span>Accounting Ledger</span>
          </a>
        </li>

        <li v-if="role === 'store'" :class="{ 'active': route().current('reports.gst*') }">
          <a
            :href="route('reports.gst')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-file-earmark-spreadsheet text-xl"></i> <span>GST Report</span>
          </a>
        </li>

        <li v-if="role === 'store'" :class="{ 'active': route().current('private*') }">
          <a
            :href="route('private.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full hover:bg-red-800/20"
          >
            <i class="bi bi-shield-lock text-xl"></i> <span class="text-red-400 font-semibold">Private Ledger</span>
          </a>
        </li>

        <li v-if="role === 'store'" :class="{ 'active': route().current('audit-logs*') }">
          <a
            :href="route('audit-logs.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-list-check text-xl"></i> <span>Audit Logs</span>
          </a>
        </li>

        <!-- Section: Settings & System -->
        <li class="pt-4 pb-1 pl-4 text-[10px] font-bold text-indigo-200 uppercase tracking-widest pointer-events-none select-none opacity-80">
          Settings
        </li>

        <li v-if="role === 'admin'" :class="{ 'active': route().current('store*') }">
          <a
            :href="route('store')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-shop text-xl"></i> <span>Stores</span>
          </a>
        </li>

        <li v-if="role === 'admin'" :class="{ 'active': route().current('role*') }">
          <a
            :href="route('role')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-shield-shaded text-xl"></i> <span>Roles</span>
          </a>
        </li>

        <li v-if="role === 'admin'" :class="{ 'active': route().current('permission*') }">
          <a
            :href="route('permission')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-key text-xl"></i> <span>Permissions</span>
          </a>
        </li>

        <li :class="{ 'active': route().current('profile.edit') }">
          <a
            :href="route('profile.edit')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-person-circle text-xl"></i> <span>Profile</span>
          </a>
        </li>

        <li class="nav-item">
          <ResponsiveNavLink
            :href="route('logout')"
            method="post"
            as="button"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <i class="bi bi-box-arrow-right text-xl"></i> Log Out
          </ResponsiveNavLink>
        </li>
      </ul>
    </nav>
  </div>
</template>
