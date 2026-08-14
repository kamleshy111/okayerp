<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  activeTab: {
    type: String,
    required: true,
  }
});
</script>

<template>
  <div class="border-b border-gray-200 bg-white px-6 pt-4 rounded-2xl shadow-sm mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
          <i class="bi bi-bell-fill text-indigo-600"></i>
          <span>Notification Control Center</span>
        </h1>
        <p class="text-xs sm:text-sm text-gray-500 mt-1">
          Manage gateways, message templates, trigger matrix, and log monitor
        </p>
      </div>
    </div>

    <!-- Sub-Nav Tabs -->
    <nav class="-mb-px flex space-x-6 overflow-x-auto no-scrollbar">
      <Link
        :href="route('notification-settings.sender-info')"
        :class="[
          'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap',
          activeTab === 'sender-info'
            ? 'border-[#2e2c92] text-[#2e2c92]'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
        ]"
      >
        <i class="bi bi-gear-wide-connected"></i>
        <span>Sender &amp; Gateways</span>
      </Link>

      <Link
        :href="route('notification-settings.templates')"
        :class="[
          'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap',
          activeTab === 'templates'
            ? 'border-[#2e2c92] text-[#2e2c92]'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
        ]"
      >
        <i class="bi bi-file-earmark-text"></i>
        <span>Message Templates</span>
      </Link>

      <Link
        :href="route('notification-settings.matrix')"
        :class="[
          'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap',
          activeTab === 'matrix'
            ? 'border-[#2e2c92] text-[#2e2c92]'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
        ]"
      >
        <i class="bi bi-sliders"></i>
        <span>Triggers &amp; Frequencies</span>
      </Link>

      <Link
        :href="route('notification-settings.logs')"
        :class="[
          'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-2 whitespace-nowrap',
          activeTab === 'logs'
            ? 'border-[#2e2c92] text-[#2e2c92]'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
        ]"
      >
        <i class="bi bi-journal-text"></i>
        <span>Logs &amp; Queue Monitor</span>
      </Link>
    </nav>
  </div>
</template>
