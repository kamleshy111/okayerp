<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NavTabs from './Partials/NavTabs.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  templates: {
    type: Array,
    required: true,
  }
});

const selectedTemplate = ref(props.templates[0] || null);

const form = useForm({
  email_subject: selectedTemplate.value?.email_subject || '',
  email_body: selectedTemplate.value?.email_body || '',
  whatsapp_body: selectedTemplate.value?.whatsapp_body || '',
  sms_body: selectedTemplate.value?.sms_body || '',
  is_active: !!selectedTemplate.value?.is_active,
});

const selectTemplate = (tmpl) => {
  selectedTemplate.value = tmpl;
  form.email_subject = tmpl.email_subject || '';
  form.email_body = tmpl.email_body || '';
  form.whatsapp_body = tmpl.whatsapp_body || '';
  form.sms_body = tmpl.sms_body || '';
  form.is_active = !!tmpl.is_active;
};

const insertTag = (field, tag) => {
  form[field] = (form[field] || '') + ' ' + tag;
};

const saveTemplate = () => {
  if (!selectedTemplate.value) return;
  form.post(route('notification-settings.templates.update', selectedTemplate.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire('Updated!', `Template '${selectedTemplate.value.name}' saved successfully.`, 'success');
    }
  });
};
</script>

<template>
  <Head title="Notification Message Templates" />
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
      <!-- Sub-Nav Tabs -->
      <NavTabs activeTab="templates" />

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Template Selector Sidebar -->
        <div class="bg-white border border-gray-200/80 rounded-2xl p-4 shadow-sm space-y-2">
          <h2 class="text-xs font-bold uppercase tracking-wider text-gray-400 px-2 mb-3">Event Templates</h2>
          
          <button
            v-for="tmpl in templates"
            :key="tmpl.id"
            @click="selectTemplate(tmpl)"
            :class="[
              'w-full text-left p-3 rounded-xl transition flex flex-col gap-1 border',
              selectedTemplate?.id === tmpl.id
                ? 'bg-[#2e2c92] text-white border-[#2e2c92] shadow-sm'
                : 'bg-gray-50/50 hover:bg-gray-100/70 border-gray-100 text-gray-800'
            ]"
          >
            <div class="flex items-center justify-between">
              <span class="text-sm font-bold truncate">{{ tmpl.name }}</span>
              <span
                :class="[
                  'text-[10px] font-bold px-2 py-0.5 rounded-full',
                  selectedTemplate?.id === tmpl.id
                    ? 'bg-white/20 text-white'
                    : tmpl.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600'
                ]"
              >
                {{ tmpl.is_active ? 'Active' : 'Off' }}
              </span>
            </div>
            <span :class="['text-[11px] font-mono', selectedTemplate?.id === tmpl.id ? 'text-indigo-200' : 'text-gray-400']">
              key: {{ tmpl.key }}
            </span>
          </button>
        </div>

        <!-- Right: Editor Area -->
        <div v-if="selectedTemplate" class="lg:col-span-2 bg-white border border-gray-200/80 rounded-2xl p-6 shadow-sm space-y-6">
          <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
              <h2 class="text-lg font-bold text-gray-900">{{ selectedTemplate.name }}</h2>
              <p class="text-xs text-gray-500 mt-0.5">Customize default text messages across channels</p>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <span class="text-xs font-semibold text-gray-600">Active</span>
              <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4" />
            </label>
          </div>

          <!-- Dynamic Tags Palette -->
          <div class="bg-indigo-50/50 border border-indigo-100 p-3 rounded-xl space-y-2">
            <span class="text-xs font-bold text-indigo-900 block">Available Dynamic Placeholders:</span>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="tag in ['{customer_name}', '{supplier_name}', '{amount}', '{invoice_no}', '{date}', '{business_name}', '{pdf_url}', '{payment_method}']"
                :key="tag"
                type="button"
                @click="insertTag('whatsapp_body', tag)"
                class="text-[11px] font-mono bg-white text-indigo-700 border border-indigo-200 hover:bg-indigo-100 px-2 py-0.5 rounded-lg transition"
                title="Click to append tag to WhatsApp message"
              >
                + {{ tag }}
              </button>
            </div>
          </div>

          <!-- WhatsApp Body -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-emerald-800 flex items-center gap-1.5">
                <i class="bi bi-whatsapp"></i> WhatsApp Message Template
              </label>
            </div>
            <textarea
              v-model="form.whatsapp_body"
              rows="3"
              class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500"
              placeholder="Enter WhatsApp message text..."
            ></textarea>
          </div>

          <!-- SMS Body -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-blue-800 flex items-center gap-1.5">
                <i class="bi bi-chat-text"></i> SMS Message Template
              </label>
            </div>
            <textarea
              v-model="form.sms_body"
              rows="2"
              class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-blue-500"
              placeholder="Enter SMS message text..."
            ></textarea>
          </div>

          <!-- Email Subject & Body -->
          <div class="space-y-3 border-t border-gray-100 pt-4">
            <div>
              <label class="block text-xs font-bold text-purple-800 mb-1">Email Subject</label>
              <input
                type="text"
                v-model="form.email_subject"
                class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500"
              />
            </div>
            <div>
              <label class="block text-xs font-bold text-purple-800 mb-1">Email Body</label>
              <textarea
                v-model="form.email_body"
                rows="3"
                class="w-full border border-gray-300 rounded-xl p-3 text-sm focus:ring-2 focus:ring-purple-500"
              ></textarea>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end pt-2">
            <button
              type="button"
              @click="saveTemplate"
              :disabled="form.processing"
              class="bg-[#2e2c92] hover:bg-[#201d70] text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-md transition disabled:opacity-50 flex items-center gap-2"
            >
              <i class="bi bi-save"></i>
              <span>{{ form.processing ? 'Saving...' : 'Save Template' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
