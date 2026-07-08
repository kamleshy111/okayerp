<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  referralUser: {
    type: Object,
    required: true,
  },
  referredSales: {
    type: Array,
    default: () => [],
  },
  achievedOffers: {
    type: Array,
    default: () => [],
  },
  inProgressOffers: {
    type: Array,
    default: () => [],
  },
  expiredOffers: {
    type: Array,
    default: () => [],
  },
});

const activeTab = ref('offers'); // 'offers' or 'sales'
const offersSubTab = ref('active'); // 'active', 'achieved', 'expired'

const totalReferredAmount = computed(() => {
  return props.referredSales.reduce((sum, sale) => sum + parseFloat(sale.sale_amount || 0), 0);
});
</script>

<template>
  <Head :title="`${props.referralUser.name} - Tracker`">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  </Head>
  <AuthenticatedLayout>
    <div class="space-y-6">
      <!-- Back button & User header -->
      <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-2">
          <a :href="route('referral-user.index')" class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-900 transition">
            <i class="bi bi-arrow-left"></i> Back to Referral Users
          </a>
          <div>
            <span class="inline-block bg-purple-100 text-purple-700 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider mb-1">
              Referral Profile
            </span>
            <h2 class="text-2xl font-bold text-gray-800">{{ props.referralUser.name }}</h2>
          </div>
          <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-500 font-medium">
            <span v-if="props.referralUser.phone" class="flex items-center gap-1.5">
              <i class="bi bi-telephone"></i> {{ props.referralUser.phone }}
            </span>
            <span v-if="props.referralUser.email" class="flex items-center gap-1.5">
              <i class="bi bi-envelope"></i> {{ props.referralUser.email }}
            </span>
          </div>
          <p v-if="props.referralUser.notes" class="text-xs bg-gray-50 p-2.5 rounded-lg border border-gray-100 text-gray-600 max-w-xl">
            <strong>Notes:</strong> {{ props.referralUser.notes }}
          </p>
        </div>

        <!-- Quick Info Stats Card -->
        <div class="bg-[#292688] text-white p-5 rounded-xl flex items-center gap-8 shadow-md">
          <div>
            <span class="block text-indigo-200 text-xs font-medium uppercase tracking-wider">Total Sales Brought</span>
            <span class="text-2xl font-extrabold">₹ {{ totalReferredAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
          </div>
          <div class="border-l border-indigo-400/30 pl-8">
            <span class="block text-indigo-200 text-xs font-medium uppercase tracking-wider">Invoices Count</span>
            <span class="text-2xl font-extrabold">{{ props.referredSales.length }}</span>
          </div>
        </div>
      </div>

      <!-- Stats Widgets Row -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
          <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 text-xl font-bold">
            <i class="bi bi-award-fill"></i>
          </div>
          <div>
            <span class="block text-gray-400 text-xs font-semibold uppercase tracking-wider">Achieved Offers</span>
            <span class="text-xl font-extrabold text-gray-800">{{ props.achievedOffers.length }}</span>
          </div>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
          <div class="w-12 h-12 bg-indigo-50 rounded-xl flex items-center justify-center text-indigo-600 text-xl font-bold">
            <i class="bi bi-hourglass-split"></i>
          </div>
          <div>
            <span class="block text-gray-400 text-xs font-semibold uppercase tracking-wider">Active In-Progress</span>
            <span class="text-xl font-extrabold text-gray-800">{{ props.inProgressOffers.length }}</span>
          </div>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 flex items-center gap-4">
          <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center text-gray-500 text-xl font-bold">
            <i class="bi bi-calendar-x-fill"></i>
          </div>
          <div>
            <span class="block text-gray-400 text-xs font-semibold uppercase tracking-wider">Expired Targets</span>
            <span class="text-xl font-extrabold text-gray-800">{{ props.expiredOffers.length }}</span>
          </div>
        </div>
      </div>

      <!-- Detail Tabs Control -->
      <div class="flex items-center gap-4 border-b border-gray-200">
        <button
          @click="activeTab = 'offers'"
          class="pb-3 text-sm font-bold uppercase tracking-wider transition-all border-b-2"
          :class="activeTab === 'offers' ? 'border-[#292688] text-[#292688]' : 'border-transparent text-gray-400 hover:text-gray-600'"
        >
          Targets Tracker
        </button>
        <button
          @click="activeTab = 'sales'"
          class="pb-3 text-sm font-bold uppercase tracking-wider transition-all border-b-2"
          :class="activeTab === 'sales' ? 'border-[#292688] text-[#292688]' : 'border-transparent text-gray-400 hover:text-gray-600'"
        >
          Referred Sales History (Invoices)
        </button>
      </div>

      <!-- Tab Content Area -->
      <div class="space-y-4">
        <!-- OFFERS TAB -->
        <div v-if="activeTab === 'offers'" class="space-y-6">
          <div class="flex items-center gap-2 bg-gray-50 p-1.5 rounded-lg border border-gray-100 w-fit">
            <button
              @click="offersSubTab = 'active'"
              class="px-4 py-2 rounded-md text-xs font-bold transition"
              :class="offersSubTab === 'active' ? 'bg-[#292688] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
              Active In-Progress ({{ props.inProgressOffers.length }})
            </button>
            <button
              @click="offersSubTab = 'achieved'"
              class="px-4 py-2 rounded-md text-xs font-bold transition"
              :class="offersSubTab === 'achieved' ? 'bg-green-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
              Achieved ({{ props.achievedOffers.length }})
            </button>
            <button
              @click="offersSubTab = 'expired'"
              class="px-4 py-2 rounded-md text-xs font-bold transition"
              :class="offersSubTab === 'expired' ? 'bg-gray-600 text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
            >
              Expired/Uncompleted ({{ props.expiredOffers.length }})
            </button>
          </div>

          <!-- Active in progress sub-tab -->
          <div v-if="offersSubTab === 'active'" class="space-y-4">
            <div v-if="props.inProgressOffers.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div v-for="offer in props.inProgressOffers" :key="offer.id" class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                <div>
                  <h3 class="font-bold text-gray-800 text-lg">{{ offer.title }}</h3>
                  <p class="text-xs text-gray-400 mt-0.5">Timeline: {{ offer.start_date }} to {{ offer.end_date }}</p>
                </div>
                <div class="bg-indigo-50/50 p-3 rounded-lg border border-indigo-100 flex items-center justify-between text-xs">
                  <div>
                    <span class="block text-gray-400 font-semibold uppercase">Reward</span>
                    <span class="font-bold text-indigo-700 text-sm">{{ offer.reward_description || 'Custom payout' }}</span>
                  </div>
                  <div class="text-right">
                    <span class="block text-gray-400 font-semibold uppercase">Target</span>
                    <span class="font-extrabold text-gray-800 text-sm">₹ {{ Number(offer.target_amount).toLocaleString('en-IN') }}</span>
                  </div>
                </div>

                <!-- Progress indicators -->
                <div class="space-y-1">
                  <div class="flex justify-between text-xs font-semibold text-gray-500">
                    <span>Referred: ₹ {{ Number(offer.total_sales).toLocaleString('en-IN') }}</span>
                    <span>{{ offer.percentage }}%</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-[#292688] h-2 rounded-full transition-all duration-300" :style="{ width: offer.percentage + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12 text-gray-400 bg-white border border-gray-100 rounded-xl">
              <i class="bi bi-hourglass-bottom text-4xl block mb-2 text-indigo-200"></i>
              <p class="font-medium">No active/in-progress offers right now.</p>
            </div>
          </div>

          <!-- Achieved sub-tab -->
          <div v-if="offersSubTab === 'achieved'" class="space-y-4">
            <div v-if="props.achievedOffers.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div v-for="offer in props.achievedOffers" :key="offer.id" class="bg-white p-5 rounded-xl border border-green-200 shadow-sm space-y-4 relative overflow-hidden">
                <!-- Ribbon achieved badge -->
                <div class="absolute top-0 right-0 bg-green-600 text-white text-[9px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-bl-lg flex items-center gap-1">
                  <i class="bi bi-check-circle-fill"></i> Achieved
                </div>

                <div>
                  <h3 class="font-bold text-gray-800 text-lg">{{ offer.title }}</h3>
                  <p class="text-xs text-gray-400 mt-0.5">Timeline: {{ offer.start_date }} to {{ offer.end_date }}</p>
                </div>
                <div class="bg-green-50 p-3 rounded-lg border border-green-100 flex items-center justify-between text-xs">
                  <div>
                    <span class="block text-gray-400 font-semibold uppercase">Reward Earned</span>
                    <span class="font-bold text-green-700 text-sm">{{ offer.reward_description || 'Custom bonus' }}</span>
                  </div>
                  <div class="text-right">
                    <span class="block text-gray-400 font-semibold uppercase">Sales Target</span>
                    <span class="font-extrabold text-gray-800 text-sm">₹ {{ Number(offer.target_amount).toLocaleString('en-IN') }}</span>
                  </div>
                </div>

                <!-- Final referral statistics -->
                <div class="text-xs font-semibold text-green-600 flex items-center gap-1">
                  <i class="bi bi-check-lg"></i> Reached ₹ {{ Number(offer.total_sales).toLocaleString('en-IN') }} referred sales amount.
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12 text-gray-400 bg-white border border-gray-100 rounded-xl">
              <i class="bi bi-award text-4xl block mb-2 text-green-200"></i>
              <p class="font-medium">No offers achieved yet.</p>
            </div>
          </div>

          <!-- Expired sub-tab -->
          <div v-if="offersSubTab === 'expired'" class="space-y-4">
            <div v-if="props.expiredOffers.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div v-for="offer in props.expiredOffers" :key="offer.id" class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm opacity-70 space-y-4">
                <div>
                  <h3 class="font-bold text-lg text-gray-500">{{ offer.title }}</h3>
                  <p class="text-xs text-gray-400 mt-0.5">Timeline: {{ offer.start_date }} to {{ offer.end_date }} (Expired)</p>
                </div>
                <div class="bg-gray-100 p-3 rounded-lg border border-gray-200 flex items-center justify-between text-xs">
                  <div>
                    <span class="block text-gray-400 font-semibold uppercase">Reward Structure</span>
                    <span class="font-bold text-gray-500 text-sm">{{ offer.reward_description || 'Custom payout' }}</span>
                  </div>
                  <div class="text-right">
                    <span class="block text-gray-400 font-semibold uppercase">Target Amount</span>
                    <span class="font-extrabold text-gray-500 text-sm">₹ {{ Number(offer.target_amount).toLocaleString('en-IN') }}</span>
                  </div>
                </div>

                <!-- Progress indicators -->
                <div class="space-y-1">
                  <div class="flex justify-between text-xs font-semibold text-gray-400">
                    <span>Referred: ₹ {{ Number(offer.total_sales).toLocaleString('en-IN') }}</span>
                    <span>{{ offer.percentage }}%</span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-gray-400 h-2 rounded-full" :style="{ width: offer.percentage + '%' }"></div>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-12 text-gray-400 bg-white border border-gray-100 rounded-xl">
              <i class="bi bi-calendar-x text-4xl block mb-2 text-gray-200"></i>
              <p class="font-medium">No expired/incomplete offers found.</p>
            </div>
          </div>
        </div>

        <!-- SALES HISTORY TAB -->
        <div v-else-if="activeTab === 'sales'">
          <div v-if="props.referredSales.length > 0" class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full table-auto text-sm">
              <thead class="bg-[#292688] text-white">
                <tr>
                  <th class="px-5 py-3 text-left">Invoice No</th>
                  <th class="px-5 py-3 text-left">Customer Name</th>
                  <th class="px-5 py-3 text-left">Referred Sale Amount</th>
                  <th class="px-5 py-3 text-left">Date Connected</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sale in props.referredSales" :key="sale.id" class="border-t border-gray-100 hover:bg-indigo-50/50 transition">
                  <td class="px-5 py-3 font-semibold text-indigo-700">
                    <a :href="route('sale.show', sale.id)" class="hover:underline">
                      Invoice #{{ sale.id }}
                    </a>
                  </td>
                  <td class="px-5 py-3 text-gray-700">{{ sale.customer_name }}</td>
                  <td class="px-5 py-3 font-bold text-gray-800">₹ {{ Number(sale.sale_amount).toFixed(2) }}</td>
                  <td class="px-5 py-3 text-gray-500">{{ sale.date }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="text-center py-12 text-gray-400 bg-white border border-gray-100 rounded-xl">
            <i class="bi bi-receipt-cutoff text-4xl block mb-2 text-indigo-200"></i>
            <p class="font-medium">No sales have been referred by this user yet.</p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
