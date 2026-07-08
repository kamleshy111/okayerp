<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

const props = defineProps({
  offers: {
    type: Array,
    default: () => [],
  },
});

const offersList = ref([...props.offers]);
const expandedOfferId = ref(null);

const togglePerformance = (id) => {
  if (expandedOfferId.value === id) {
    expandedOfferId.value = null;
  } else {
    expandedOfferId.value = id;
  }
};

const deleteOffer = async (id) => {
  if (!confirm('Are you sure you want to delete this offer?')) return;
  try {
    await axios.delete(`/offer/destroy/${id}`);
    offersList.value = offersList.value.filter(o => o.id !== id);
    toast.success('Offer deleted successfully.');
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to delete offer.');
  }
};
</script>

<template>
  <Head title="Offers Module">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  </Head>
  <AuthenticatedLayout>
    <div class="bg-white p-8 rounded-xl shadow-md space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-bold text-[#292688]">Offer Targets</h2>
          <p class="text-sm text-gray-500 mt-1">Manage target offers, timeline periods, and view referral performance trackers</p>
        </div>
        <a
          :href="route('offer.create')"
          class="inline-flex items-center gap-2 bg-[#292688] hover:bg-[#1e1d6a] text-white px-5 py-2.5 rounded-lg font-semibold shadow transition"
        >
          <i class="bi bi-gift-fill"></i> Add New Offer
        </a>
      </div>

      <!-- Offers Grid -->
      <div v-if="offersList.length > 0" class="space-y-6">
        <div v-for="offer in offersList" :key="offer.id" class="border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow transition">
          <!-- Offer main header -->
          <div class="bg-gray-50 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="inline-block bg-purple-100 text-purple-700 text-[11px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider">
                  Target Offer
                </span>
                <span 
                  v-if="offer.is_expired" 
                  class="inline-block bg-red-100 text-red-700 text-[11px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider"
                >
                  Expired
                </span>
                <span 
                  v-else 
                  class="inline-block bg-green-100 text-green-700 text-[11px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider"
                >
                  Active
                </span>
              </div>
              <h3 class="text-lg font-bold text-gray-800">{{ offer.title }}</h3>
              <p class="text-xs text-gray-500 flex items-center gap-1">
                <i class="bi bi-calendar-range"></i> Duration: {{ offer.start_date }} to {{ offer.end_date }}
              </p>
            </div>

            <!-- Target metrics -->
            <div class="flex items-center gap-6">
              <div class="text-right">
                <span class="block text-xs text-gray-400 font-medium">Target Sales</span>
                <span class="text-lg font-bold text-[#292688]">₹ {{ Number(offer.target_amount).toLocaleString('en-IN') }}</span>
              </div>
              <div class="text-right border-l border-gray-200 pl-6">
                <span class="block text-xs text-gray-400 font-medium">Reward Payout</span>
                <span class="text-sm font-semibold text-green-700">{{ offer.reward_description || 'No custom reward description' }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <button
                @click="togglePerformance(offer.id)"
                class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
              >
                <i :class="expandedOfferId === offer.id ? 'bi-chevron-up' : 'bi-chevron-down'"></i> Tracker
              </button>
              <a
                :href="route('offer.edit', offer.id)"
                class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
              >
                <i class="bi bi-pencil-fill"></i> Edit
              </a>
              <button
                @click="deleteOffer(offer.id)"
                class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-1.5"
              >
                <i class="bi bi-trash-fill"></i> Delete
              </button>
            </div>
          </div>

          <!-- Performance details panel (collapsible) -->
          <div v-show="expandedOfferId === offer.id" class="p-5 bg-white border-t border-gray-100 space-y-4">
            <h4 class="text-sm font-bold text-gray-700 mb-2 flex items-center gap-1">
              <i class="bi bi-person-fill-check"></i> Referral Performance Progress List
            </h4>
            <div v-if="offer.performance.length > 0" class="space-y-4">
              <div v-for="user in offer.performance" :key="user.referral_user_id" class="p-3.5 bg-gray-50 rounded-lg border border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="w-full md:w-1/4">
                  <span class="block font-semibold text-gray-800">{{ user.name }}</span>
                  <span class="text-xs text-gray-500">{{ user.phone || '—' }}</span>
                </div>

                <div class="w-full md:w-1/2 flex items-center gap-3">
                  <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                    <div
                      class="h-2.5 rounded-full transition-all duration-500"
                      :class="user.achieved ? 'bg-green-600' : (offer.is_expired ? 'bg-gray-400' : 'bg-indigo-600')"
                      :style="{ width: user.percentage + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs font-bold text-gray-600 min-w-[35px]">{{ user.percentage }}%</span>
                </div>

                <div class="w-full md:w-1/4 flex items-center justify-end gap-3 text-right">
                  <div>
                    <span class="block text-xs text-gray-400">Total Referred Sales</span>
                    <span class="font-bold text-gray-800">₹ {{ Number(user.total_sales).toLocaleString('en-IN') }}</span>
                  </div>
                  <div>
                    <span
                      v-if="user.achieved"
                      class="inline-flex items-center gap-1 bg-green-100 text-green-700 text-xs font-bold px-2.5 py-1 rounded-full"
                    >
                      <i class="bi bi-check-circle-fill"></i> Achieved
                    </span>
                    <span
                      v-else-if="offer.is_expired"
                      class="inline-flex items-center gap-1 bg-red-100 text-red-700 text-xs font-bold px-2.5 py-1 rounded-full"
                    >
                      <i class="bi bi-x-circle-fill"></i> Expired
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 text-xs font-bold px-2.5 py-1 rounded-full"
                    >
                      In Progress
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-6 text-gray-400 text-sm">
              No referral users found to track.
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="text-center py-16 text-gray-400">
        <i class="bi bi-gift text-5xl mb-4 block text-indigo-200"></i>
        <p class="text-lg font-medium">No target offers set yet.</p>
        <p class="text-sm mt-1">Click <strong>Add New Offer</strong> to create a reward structure program.</p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
