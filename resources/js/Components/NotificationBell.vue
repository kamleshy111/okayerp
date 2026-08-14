<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const isOpen = ref(false);
const unreadCount = ref(0);
const notifications = ref([]);
const isLoading = ref(false);
let pollInterval = null;

const fetchNotifications = async () => {
  try {
    const res = await axios.get(route('notifications.header-list'));
    notifications.value = res.data.notifications || [];
    unreadCount.value = res.data.unreadCount || 0;
  } catch (err) {
    console.error('Failed to fetch header notifications:', err);
  }
};

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    fetchNotifications();
  }
};

const markRead = async (item) => {
  if (!item.is_read) {
    try {
      await axios.post(route('notifications.mark-read', item.id));
      item.is_read = true;
      if (unreadCount.value > 0) unreadCount.value--;
    } catch (err) {
      console.error('Failed to mark notification read:', err);
    }
  }
  if (item.action_url) {
    isOpen.value = false;
    router.visit(item.action_url);
  }
};

const markAllRead = async () => {
  try {
    await axios.post(route('notifications.mark-all-read'));
    notifications.value.forEach(n => n.is_read = true);
    unreadCount.value = 0;
  } catch (err) {
    console.error('Failed to mark all notifications read:', err);
  }
};

const handleClickOutside = (e) => {
  const container = document.getElementById('notification-bell-container');
  if (container && !container.contains(e.target)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  fetchNotifications();
  document.addEventListener('click', handleClickOutside);
  // Poll every 30 seconds for live notifications
  pollInterval = setInterval(fetchNotifications, 30000);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <div id="notification-bell-container" class="relative">
    <!-- Bell Trigger Button -->
    <button
      @click="toggleDropdown"
      type="button"
      class="relative p-2 rounded-full text-gray-500 hover:text-indigo-600 hover:bg-gray-100 focus:outline-none transition"
      title="Notifications"
    >
      <i class="bi bi-bell text-xl"></i>

      <!-- Red Unread Badge -->
      <span
        v-if="unreadCount > 0"
        class="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-extrabold text-white shadow-sm ring-2 ring-white animate-pulse"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>
    </button>

    <!-- Dropdown Popover -->
    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-80 sm:w-96 rounded-2xl bg-white shadow-2xl border border-gray-100 z-50 overflow-hidden transform transition-all duration-200"
    >
      <!-- Header -->
      <div class="px-4 py-3 bg-[#2e2c92] text-white flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="bi bi-bell-fill text-lg text-indigo-200"></i>
          <span class="font-bold text-sm">Notifications</span>
          <span v-if="unreadCount > 0" class="bg-indigo-500/40 text-white text-xs px-2 py-0.5 rounded-full font-semibold">
            {{ unreadCount }} new
          </span>
        </div>
        <button
          v-if="unreadCount > 0"
          @click="markAllRead"
          class="text-xs text-indigo-200 hover:text-white transition hover:underline"
        >
          Mark all read
        </button>
      </div>

      <!-- Notification Items List -->
      <div class="max-h-80 overflow-y-auto divide-y divide-gray-100">
        <div v-if="notifications.length === 0" class="p-6 text-center text-gray-400">
          <i class="bi bi-bell-slash text-3xl block mb-2 opacity-50"></i>
          <p class="text-xs">No notifications yet</p>
        </div>

        <div
          v-for="item in notifications"
          :key="item.id"
          @click="markRead(item)"
          :class="[
            'p-3.5 flex gap-3 items-start transition cursor-pointer hover:bg-gray-50',
            !item.is_read ? 'bg-indigo-50/40' : 'bg-white'
          ]"
        >
          <!-- Category Icon -->
          <div
            :class="[
              'w-8 h-8 rounded-full flex items-center justify-center shrink-0 mt-0.5 text-sm',
              item.type === 'sale' ? 'bg-emerald-100 text-emerald-700' :
              item.type === 'purchase' ? 'bg-blue-100 text-blue-700' :
              item.type === 'payment' ? 'bg-purple-100 text-purple-700' :
              item.type === 'stock' ? 'bg-amber-100 text-amber-700' :
              item.type === 'aging' ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-700'
            ]"
          >
            <i :class="['bi', item.icon || 'bi-bell']"></i>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between">
              <p class="text-xs font-bold text-gray-900 truncate">{{ item.title }}</p>
              <span class="text-[10px] text-gray-400 whitespace-nowrap ml-2">{{ item.created_at_human }}</span>
            </div>
            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ item.message }}</p>
          </div>

          <!-- Unread Dot -->
          <span v-if="!item.is_read" class="w-2 h-2 rounded-full bg-indigo-600 shrink-0 mt-2"></span>
        </div>
      </div>

      <!-- Footer Link -->
      <div class="p-2.5 bg-gray-50 border-t border-gray-100 text-center">
        <a
          :href="route('notification-settings.logs')"
          class="text-xs font-semibold text-[#2e2c92] hover:underline"
        >
          View Notification Logs & History &rarr;
        </a>
      </div>
    </div>
  </div>
</template>
