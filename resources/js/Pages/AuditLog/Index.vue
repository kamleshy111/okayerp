<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
  logs: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({ search: '', action_filter: '' }),
  },
});

const search = ref(props.filters.search || '');
const actionFilter = ref(props.filters.action_filter || '');
const expandedLogId = ref(null);

const toggleExpand = (logId) => {
  if (expandedLogId.value === logId) {
    expandedLogId.value = null;
  } else {
    expandedLogId.value = logId;
  }
};

const handleFilter = () => {
  router.get(
    route('audit-logs.index'),
    { search: search.value, action_filter: actionFilter.value },
    { preserveState: true, replace: true }
  );
};

// Watchers to trigger filter on change
watch([search, actionFilter], () => {
  handleFilter();
});

const getActionBadgeClass = (action) => {
  switch (action) {
    case 'CREATE':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'UPDATE':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'DELETE':
      return 'bg-rose-100 text-rose-800 border-rose-200';
    default:
      return 'bg-gray-100 text-gray-800 border-gray-200';
  }
};

const getChangedFields = (log) => {
  const fields = [];
  const oldVal = log.old_values || {};
  const newVal = log.new_values || {};

  if (log.action === 'CREATE') {
    Object.keys(newVal).forEach((key) => {
      fields.push({
        key,
        old: null,
        new: newVal[key],
      });
    });
  } else if (log.action === 'DELETE') {
    Object.keys(oldVal).forEach((key) => {
      fields.push({
        key,
        old: oldVal[key],
        new: null,
      });
    });
  } else {
    // UPDATE
    const allKeys = new Set([...Object.keys(oldVal), ...Object.keys(newVal)]);
    allKeys.forEach((key) => {
      fields.push({
        key,
        old: oldVal[key],
        new: newVal[key],
      });
    });
  }
  return fields;
};

const formatValue = (val) => {
  if (val === null || val === undefined) return 'N/A';
  if (typeof val === 'object') return JSON.stringify(val);
  return String(val);
};
</script>

<template>
  <Head title="System Audit Logs" />

  <AuthenticatedLayout>
    <div class="p-6 max-w-7xl mx-auto">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <h1 class="text-3xl font-extrabold text-gray-800 flex items-center gap-3">
            <span>📋</span> System Audit Logs
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            Track user activities and critical model modifications.
          </p>
        </div>
      </div>

      <!-- Filters Panel -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-96 relative">
          <span class="absolute left-3 top-2.5 text-gray-400">🔍</span>
          <input
            v-model="search"
            type="text"
            placeholder="Search by user, ID, model..."
            class="w-full pl-9 pr-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
          />
        </div>

        <div class="w-full md:w-48">
          <select
            v-model="actionFilter"
            class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200"
          >
            <option value="">All Actions</option>
            <option value="CREATE">CREATE</option>
            <option value="UPDATE">UPDATE</option>
            <option value="DELETE">DELETE</option>
          </select>
        </div>
      </div>

      <!-- Logs List -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider w-16"></th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Timestamp</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">User</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Action</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Target Model</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Target ID</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Origin IP</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <template v-for="log in logs.data" :key="log.id">
                <!-- Main Log Row -->
                <tr class="hover:bg-slate-50/50 transition-colors duration-150">
                  <td class="px-6 py-4 text-center">
                    <button
                      @click="toggleExpand(log.id)"
                      class="text-gray-400 hover:text-indigo-600 transition-colors duration-150 focus:outline-none"
                    >
                      <span class="text-sm font-bold">{{ expandedLogId === log.id ? '▼' : '▶' }}</span>
                    </button>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                    {{ log.created_at }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-800">
                    {{ log.user_name }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="['px-2.5 py-1 text-xs font-bold rounded-full border', getActionBadgeClass(log.action)]">
                      {{ log.action }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ log.model_type }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-700">
                    #{{ log.model_id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                    {{ log.ip_address || 'N/A' }}
                  </td>
                </tr>

                <!-- Expanded Values Diff Row -->
                <tr v-if="expandedLogId === log.id">
                  <td colspan="7" class="bg-slate-50/50 px-8 py-6">
                    <div class="bg-white rounded-lg border border-gray-100 p-4 shadow-inner">
                      <div class="mb-4 flex flex-col sm:flex-row justify-between text-xs text-gray-400 gap-2 border-b border-gray-100 pb-3">
                        <div>
                          <strong>User Agent:</strong> {{ log.user_agent || 'N/A' }}
                        </div>
                      </div>
                      
                      <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2">
                        <span>🔍</span> Modification Details
                      </h4>

                      <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                          <thead>
                            <tr class="text-left text-xs font-bold text-gray-400 uppercase">
                              <th class="py-2 pr-4">Attribute</th>
                              <th class="py-2 px-4">Old Value</th>
                              <th class="py-2 pl-4">New Value</th>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-50">
                            <tr v-for="field in getChangedFields(log)" :key="field.key" class="align-top">
                              <td class="py-3 pr-4 font-mono font-bold text-slate-600">{{ field.key }}</td>
                              <td class="py-3 px-4">
                                <span
                                  v-if="field.old !== null && field.old !== undefined"
                                  class="inline-block bg-rose-50 border border-rose-100 text-rose-700 rounded px-2 py-1 font-mono text-xs max-w-xs break-all"
                                >
                                  {{ formatValue(field.old) }}
                                </span>
                                <span v-else class="text-gray-300 italic text-xs">None</span>
                              </td>
                              <td class="py-3 pl-4">
                                <span
                                  v-if="field.new !== null && field.new !== undefined"
                                  class="inline-block bg-emerald-50 border border-emerald-100 text-emerald-700 rounded px-2 py-1 font-mono text-xs max-w-xs break-all"
                                >
                                  {{ formatValue(field.new) }}
                                </span>
                                <span v-else class="text-gray-300 italic text-xs">None</span>
                              </td>
                            </tr>
                            <tr v-if="getChangedFields(log).length === 0">
                              <td colspan="3" class="py-3 text-center text-gray-400 italic">
                                No modifications tracked for this event.
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>

              <tr v-if="logs.data.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                  <div class="text-4xl mb-2">📭</div>
                  No audit logs found. Try adjusting your filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="logs.total > logs.per_page" class="flex items-center justify-between px-6 py-4 bg-slate-50 border-t border-gray-100">
          <div class="flex-1 flex justify-between sm:hidden">
            <Link
              :href="logs.prev_page_url || '#'"
              :class="['relative inline-flex items-center px-4 py-2 border border-gray-200 text-xs font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50', { 'opacity-50 pointer-events-none': !logs.prev_page_url }]"
            >
              Previous
            </Link>
            <Link
              :href="logs.next_page_url || '#'"
              :class="['ml-3 relative inline-flex items-center px-4 py-2 border border-gray-200 text-xs font-semibold rounded-lg text-gray-700 bg-white hover:bg-gray-50', { 'opacity-50 pointer-events-none': !logs.next_page_url }]"
            >
              Next
            </Link>
          </div>
          <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
              <p class="text-xs text-gray-500 font-semibold">
                Showing <span class="font-bold text-gray-700">{{ logs.from }}</span> to <span class="font-bold text-gray-700">{{ logs.to }}</span> of <span class="font-bold text-gray-700">{{ logs.total }}</span> entries
              </p>
            </div>
            <div>
              <nav class="relative z-0 inline-flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
                <template v-for="(link, i) in logs.links" :key="i">
                  <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                      link.active ? 'z-10 bg-indigo-600 border-indigo-600 text-white font-bold' : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50',
                      'relative inline-flex items-center px-3 py-1.5 border text-xs font-medium first:rounded-l-lg last:rounded-r-lg transition-colors duration-150'
                    ]"
                    v-html="link.label"
                  />
                  <span
                    v-else
                    class="relative inline-flex items-center px-3 py-1.5 border border-gray-200 bg-white text-xs font-medium text-gray-300 first:rounded-l-lg last:rounded-r-lg"
                    v-html="link.label"
                  />
                </template>
              </nav>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
