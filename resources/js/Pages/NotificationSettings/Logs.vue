<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NavTabs from './Partials/NavTabs.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  logs: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ channel: 'all', status: 'all' })
  }
});

const channelFilter = ref(props.filters.channel || 'all');
const statusFilter = ref(props.filters.status || 'all');

const applyFilter = () => {
  router.get(route('notification-settings.logs'), {
    channel: channelFilter.value,
    status: statusFilter.value,
  }, { preserveState: true, replace: true });
};

watch([channelFilter, statusFilter], () => {
  applyFilter();
});

const clearLogs = () => {
  Swal.fire({
    title: 'Clear Notification Logs?',
    text: 'Are you sure you want to delete all notification history logs?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, clear all!'
  }).then((res) => {
    if (res.isConfirmed) {
      router.post(route('notification-settings.logs.clear'), {}, {
        onSuccess: () => {
          Swal.fire('Cleared!', 'Notification logs have been deleted.', 'success');
        }
      });
    }
  });
};
</script>

<template>
  <Head title="Notification Logs &amp; Queue Monitor" />
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
      <!-- Sub-Nav Tabs -->
      <NavTabs activeTab="logs" />

      <!-- Filter Controls & Clear Action -->
      <div class="bg-white border border-gray-200/80 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Filter by Channel</label>
            <select v-model="channelFilter" class="border border-gray-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white focus:ring-2 focus:ring-indigo-500">
              <option value="all">All Channels</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="sms">SMS</option>
              <option value="email">Email</option>
              <option value="in_app">In-App Header</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Filter by Status</label>
            <select v-model="statusFilter" class="border border-gray-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white focus:ring-2 focus:ring-indigo-500">
              <option value="all">All Statuses</option>
              <option value="sent">Sent</option>
              <option value="failed">Failed</option>
              <option value="pending">Pending</option>
            </select>
          </div>
        </div>

        <button
          @click="clearLogs"
          class="text-xs font-bold text-red-600 hover:text-red-800 border border-red-200 hover:bg-red-50 px-4 py-2 rounded-xl transition flex items-center gap-1.5 self-start md:self-auto"
        >
          <i class="bi bi-trash"></i>
          <span>Clear Logs</span>
        </button>
      </div>

      <!-- Logs Table -->
      <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left">
            <thead class="bg-[#2e2c92] text-white text-xs uppercase tracking-wider">
              <tr>
                <th class="px-5 py-3.5">Sent Date</th>
                <th class="px-5 py-3.5">Channel</th>
                <th class="px-5 py-3.5">Recipient</th>
                <th class="px-5 py-3.5">Event / Subject</th>
                <th class="px-5 py-3.5">Message Content</th>
                <th class="px-5 py-3.5 text-center">Status</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-gray-100">
              <tr v-if="logs.data.length === 0">
                <td colspan="6" class="text-center py-16 text-gray-400">
                  <i class="bi bi-journal-x text-4xl block mb-2 opacity-50"></i>
                  <p class="text-xs font-semibold">No notification log entries found</p>
                </td>
              </tr>

              <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50/80 transition">
                <!-- Date -->
                <td class="px-5 py-3.5 text-xs text-gray-500 whitespace-nowrap">
                  {{ new Date(log.created_at).toLocaleString() }}
                </td>

                <!-- Channel Badge -->
                <td class="px-5 py-3.5 whitespace-nowrap">
                  <span
                    :class="[
                      'text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full border',
                      log.channel === 'whatsapp' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                      log.channel === 'sms' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                      log.channel === 'email' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-gray-50 text-gray-700 border-gray-200'
                    ]"
                  >
                    {{ log.channel }}
                  </span>
                </td>

                <!-- Recipient -->
                <td class="px-5 py-3.5 font-mono text-xs font-bold text-gray-800 whitespace-nowrap">
                  {{ log.recipient || '—' }}
                </td>

                <!-- Event Key / Subject -->
                <td class="px-5 py-3.5 text-xs font-bold text-gray-900 whitespace-nowrap">
                  {{ log.event_key || log.subject || '—' }}
                </td>

                <!-- Message Body -->
                <td class="px-5 py-3.5 text-xs text-gray-600 max-w-xs truncate" :title="log.body">
                  {{ log.body }}
                </td>

                <!-- Status Badge -->
                <td class="px-5 py-3.5 text-center whitespace-nowrap">
                  <span
                    :class="[
                      'text-xs font-bold px-2.5 py-1 rounded-full',
                      log.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                    ]"
                  >
                    {{ log.status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
