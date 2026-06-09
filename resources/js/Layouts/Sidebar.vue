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

const hasPermission = (permission) => {
  return page.props.auth.user?.permissions?.includes(permission);
};

onMounted(() => {
  console.log("Sidebar Auth User Permissions:", page.props.auth.user?.permissions);
});
</script>

<template>
  <!-- Sidebar -->
  <div>
    <nav class="flex-1">
      <ul class="space-y-2 pl-3 mt-8">
        <li :class="{ 'active': route().current('dashboard') }">
          <a
            :href="route('dashboard')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">🏠</span> <span>Dashboard</span>
          </a>
        </li>

        <li v-if="role === 'admin'" :class="{ 'active': route().current('store*') }">
          <a
            :href="route('store')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">👥</span> <span>Stores</span>
          </a>
        </li>

        <li v-if="role === 'admin'" :class="{ 'active': route().current('role*') }">
          <a
            :href="route('role')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">🛡️</span> <span>Roles</span>
          </a>
        </li>

        <li v-if="role === 'admin'" :class="{ 'active': route().current('permission*') }">
          <a
            :href="route('permission')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">🔑</span> <span>Permissions</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('customer manage')" :class="{ 'active': route().current('customer*') }">
          <a
            :href="route('customer')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">👥</span> <span>Customers</span>
          </a>
        </li>
        

        <li v-if="role === 'store' && hasPermission('supplier manage')" :class="{ 'active': route().current('supplier*') }">
          <a
            :href="route('supplier')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">👥</span> <span>Suppliers</span>
          </a>
        </li>

       <li v-if="role === 'store' && hasPermission('category manage')" :class="{ 'active': route().current('category*') }">
          <a
            :href="route('category')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">📦</span> <span>Categories</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('expense category manage')" :class="{ 'active': route().current('expense-category*') }">
          <a
            :href="route('expense-category')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">📦</span> <span>Expense Categories</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('expense manage')" :class="{ 'active': route().current('expense*') && !route().current('expense-category*') }">
          <a
            :href="route('expense')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">💰</span> <span>Expenses</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('product manage')" :class="{ 'active': route().current('product*') }">
          <a
            :href="route('product')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">📦</span> <span>Products</span>
          </a>
        </li>

        <li v-if="role === 'store' && hasPermission('purchase manage')" :class="{ 'active': route().current('purchase*') }">
          <a
            :href="route('purchase')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">📦</span> <span>Purchase</span>
          </a>
        </li> 

        <li v-if="role === 'store' && hasPermission('sale manage')" :class="{ 'active': route().current('sale*') }">
          <a
            :href="route('sale')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">📦</span> <span>Sales</span>
          </a>
        </li> 

        <li v-if="role === 'store' && hasPermission('payments customer manage')" :class="{ 'active': route().current('paymentsCustomer*') }">
          <a
            :href="route('paymentsCustomer')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">💰</span> <span>Customers Paymets</span>
          </a>
        </li> 

        <li v-if="role === 'store' && hasPermission('payment supplier manage')" :class="{ 'active': route().current('paymentSupplier*') }">
          <a
            :href="route('paymentSupplier')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">💰</span> <span>Suppliers Paymets</span>
          </a>
        </li> 

        <li v-if="role === 'store'" :class="{ 'active': route().current('private*') }">
          <a
            :href="route('private.index')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full hover:bg-red-800/20"
          >
            <span class="text-xl">🔒</span> <span class="text-red-400 font-semibold">Private Ledger</span>
          </a>
        </li>

        <li :class="{ 'active': route().current('profile.edit') }">
          <a
            :href="route('profile.edit')"
            class="flex items-center gap-3 px-4 py-2 rounded-l-full"
          >
            <span class="text-xl">👥</span> <span>Profile</span>
          </a>
        </li>
        <li class="nav-item">
              <ResponsiveNavLink
                :href="route('logout')"
                method="post"
                as="button"
                 class="flex items-center gap-3 px-4 py-2 rounded-l-full"
              >
                <span class="text-xl">↩️</span>Log Out
              </ResponsiveNavLink>
        </li>
      </ul>
    </nav>
  </div>
</template>
