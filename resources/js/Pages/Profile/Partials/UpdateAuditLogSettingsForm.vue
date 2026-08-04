<script setup>
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { toast } from "vue3-toastify";
import "vue3-toastify/dist/index.css";

const page = usePage();
const user = page.props.auth.user;

const auditLogsEnabled = ref(!!user.audit_logs_enabled);
const isToggling = ref(false);

const toggleLogging = () => {
  isToggling.value = true;
  const newValue = !auditLogsEnabled.value;
  router.post(
    route('audit-logs.toggle'),
    { audit_logs_enabled: newValue },
    {
      preserveScroll: true,
      onSuccess: () => {
        auditLogsEnabled.value = newValue;
        toast.success(newValue ? 'Global system log recording enabled for all stores.' : 'Global system log recording disabled for all stores.');
      },
      onError: () => {
        toast.error('Failed to update global log recording setting');
      },
      onFinish: () => {
        isToggling.value = false;
      }
    }
  );
};
</script>

<template>
    <section class="max-w-none mx-auto">
        <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 pb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <span>📋</span> Audit Log Settings
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Enable or disable recording of audit logs across all stores
                </p>
            </div>

            <div class="flex items-center gap-3 bg-gray-50 px-4 py-2.5 rounded-xl border border-gray-200">
                <span class="text-sm font-semibold text-gray-700">Record System Logs:</span>
                <button
                    @click="toggleLogging"
                    :disabled="isToggling"
                    type="button"
                    :class="[
                        auditLogsEnabled ? 'bg-[#2e2c92]' : 'bg-gray-300',
                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50'
                    ]"
                >
                    <span
                        :class="[
                            auditLogsEnabled ? 'translate-x-5' : 'translate-x-0',
                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                        ]"
                    />
                </button>
                <span :class="['text-xs font-bold px-2.5 py-1 rounded-full border', auditLogsEnabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200']">
                    {{ auditLogsEnabled ? 'ENABLED' : 'DISABLED' }}
                </span>
            </div>
        </header>

        <div class="mt-4 text-xs text-gray-500 leading-relaxed bg-amber-50/50 p-4 rounded-lg border border-amber-100">
            <span class="font-bold text-amber-800">Note:</span> When <strong>DISABLED</strong>, no audit log entries (CREATE, UPDATE, DELETE) will be stored for any store or system action. When <strong>ENABLED</strong>, all store actions and system changes are tracked in the System Audit Logs.
        </div>
    </section>
</template>
