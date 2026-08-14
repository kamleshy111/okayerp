<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NavTabs from './Partials/NavTabs.vue';
import { Head, useForm } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const props = defineProps({
  triggers: {
    type: Array,
    required: true,
  }
});

const form = useForm({
  triggers: props.triggers.map(t => ({
    id: t.id,
    event_key: t.event_key,
    event_name: t.event_name,
    frequency: t.frequency || 'instant',
    whatsapp_enabled: !!t.whatsapp_enabled,
    sms_enabled: !!t.sms_enabled,
    email_enabled: !!t.email_enabled,
    in_app_enabled: !!t.in_app_enabled,
  }))
});

const saveMatrix = () => {
  form.post(route('notification-settings.matrix.update'), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire('Saved!', 'Notification Triggers Matrix updated successfully.', 'success');
    }
  });
};
</script>

<template>
  <Head title="Notification Triggers Matrix" />
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
      <!-- Sub-Nav Tabs -->
      <NavTabs activeTab="matrix" />

      <form @submit.prevent="saveMatrix" class="space-y-6">
        <div class="bg-white border border-gray-200/80 rounded-2xl shadow-sm overflow-hidden">
          <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h2 class="text-lg font-bold text-gray-900">Triggers &amp; Frequencies Matrix</h2>
              <p class="text-xs text-gray-500 mt-0.5">Control channel delivery and automated frequency per event</p>
            </div>
            <button
              type="submit"
              :disabled="form.processing"
              class="bg-[#2e2c92] hover:bg-[#201d70] text-white px-5 py-2.5 rounded-xl font-bold text-sm shadow-md transition disabled:opacity-50 flex items-center gap-2 self-start sm:self-auto"
            >
              <i class="bi bi-check-circle"></i>
              <span>{{ form.processing ? 'Saving...' : 'Save Matrix' }}</span>
            </button>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
              <thead class="bg-[#2e2c92] text-white text-xs uppercase tracking-wider">
                <tr>
                  <th class="px-5 py-3.5">Event Name</th>
                  <th class="px-5 py-3.5 text-center">Frequency</th>
                  <th class="px-5 py-3.5 text-center">In-App &amp; Push Alerts</th>
                  <th class="px-5 py-3.5 text-center">WhatsApp</th>
                  <th class="px-5 py-3.5 text-center">SMS</th>
                  <th class="px-5 py-3.5 text-center">Email</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="(trig, idx) in form.triggers" :key="trig.id" class="hover:bg-gray-50/80 transition">
                  <!-- Event Name -->
                  <td class="px-5 py-4">
                    <span class="font-bold text-gray-900 block text-sm">{{ trig.event_name }}</span>
                    <span class="font-mono text-[11px] text-gray-400">key: {{ trig.event_key }}</span>
                  </td>

                  <!-- Frequency Selector -->
                  <td class="px-5 py-4 text-center">
                    <select
                      v-model="trig.frequency"
                      class="border border-gray-300 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white focus:ring-2 focus:ring-indigo-500"
                    >
                      <option value="instant">Instant Trigger</option>
                      <option value="daily">Daily</option>
                      <option value="weekly">Weekly (1 Time / Week)</option>
                      <option value="twice_a_week">2 Times in Week</option>
                      <option value="three_times_a_week">3 Times in Week</option>
                      <option value="once_a_month">1 Time in Month</option>
                      <option value="twice_a_month">2 Times in Month</option>
                      <option value="disabled">Disabled</option>
                    </select>
                  </td>

                  <!-- In-App Switch -->
                  <td class="px-5 py-4 text-center">
                    <input type="checkbox" v-model="trig.in_app_enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4" />
                  </td>

                  <!-- WhatsApp Switch -->
                  <td class="px-5 py-4 text-center">
                    <input type="checkbox" v-model="trig.whatsapp_enabled" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 h-4 w-4" />
                  </td>

                  <!-- SMS Switch -->
                  <td class="px-5 py-4 text-center">
                    <input type="checkbox" v-model="trig.sms_enabled" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 h-4 w-4" />
                  </td>

                  <!-- Email Switch -->
                  <td class="px-5 py-4 text-center">
                    <input type="checkbox" v-model="trig.email_enabled" class="rounded border-purple-300 text-purple-600 focus:ring-purple-500 h-4 w-4" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
