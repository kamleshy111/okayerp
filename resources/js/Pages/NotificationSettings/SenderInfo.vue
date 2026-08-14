<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import NavTabs from './Partials/NavTabs.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
  setting: {
    type: Object,
    required: true,
  }
});

const form = useForm({
  whatsapp_enabled: !!props.setting.whatsapp_enabled,
  whatsapp_provider: props.setting.whatsapp_provider || 'whatsapp_web',
  whatsapp_api_url: props.setting.whatsapp_api_url || '',
  whatsapp_api_key: props.setting.whatsapp_api_key || '',
  whatsapp_app_name: props.setting.whatsapp_app_name || '',
  meta_whatsapp_phone_number_id: props.setting.meta_whatsapp_phone_number_id || '',
  meta_whatsapp_access_token: props.setting.meta_whatsapp_access_token || '',
  
  sms_enabled: !!props.setting.sms_enabled,
  sms_api_url: props.setting.sms_api_url || '',
  sms_api_key: props.setting.sms_api_key || '',
  sms_sender_name: props.setting.sms_sender_name || '',

  email_enabled: !!props.setting.email_enabled,
  in_app_enabled: !!props.setting.in_app_enabled,

  fcm_enabled: !!props.setting.fcm_enabled,
  firebase_project_id: props.setting.firebase_project_id || '',
  firebase_credentials_json: props.setting.firebase_credentials_json || '',
});

// Live QR Code Scanner state
const qrLoading = ref(false);
const qrData = ref(null);
const qrError = ref(null);

const fetchQrCode = async () => {
  if (qrLoading.value) return;
  qrLoading.value = true;
  qrError.value = null;
  try {
    const res = await axios.get(route('notification-settings.whatsapp-qr'));
    qrData.value = res.data;
  } catch (err) {
    qrError.value = 'Could not fetch QR code from WhatsApp Web gateway.';
  } finally {
    qrLoading.value = false;
  }
};

// WhatsApp Tester state
const testPhone = ref('');
const testMessage = ref('Hello! This is a test WhatsApp notification from OkayERP.');
const testLoading = ref(false);

const sendTestWhatsApp = async () => {
  if (!testPhone.value) {
    Swal.fire('Phone Required', 'Please enter a valid mobile number to test.', 'warning');
    return;
  }
  testLoading.value = true;
  try {
    const res = await axios.post(route('notification-settings.test-whatsapp'), {
      phone: testPhone.value,
      message: testMessage.value,
    });
    if (res.data.success) {
      Swal.fire('Dispatched!', res.data.message, 'success');
    } else {
      Swal.fire('Dispatch Failed', res.data.message, 'error');
    }
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message || 'Failed to send test message.', 'error');
  } finally {
    testLoading.value = false;
  }
};

const saveSettings = () => {
  form.post(route('notification-settings.sender-info.update'), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire('Saved!', 'Notification Gateway Settings updated successfully.', 'success');
      if (form.whatsapp_provider === 'whatsapp_web') {
        fetchQrCode();
      }
    }
  });
};

onMounted(() => {
  if (form.whatsapp_provider === 'whatsapp_web') {
    fetchQrCode();
  }
});
</script>

<template>
  <Head title="Notification Gateway Settings" />
  <AuthenticatedLayout>
    <div class="p-4 sm:p-6 max-w-7xl mx-auto space-y-6">
      <!-- Sub-Nav Tabs -->
      <NavTabs activeTab="sender-info" />

      <form @submit.prevent="saveSettings" class="space-y-6">
        
        <!-- Channels Master Toggles -->
        <div class="bg-white border border-gray-200/80 rounded-2xl p-6 shadow-sm">
          <h2 class="text-lg font-bold text-gray-900 mb-1">Master Delivery Channels</h2>
          <p class="text-xs text-gray-500 mb-6">Enable or disable delivery channels globally for your store</p>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <!-- WhatsApp Switch -->
            <label class="flex items-center justify-between p-4 rounded-xl border border-emerald-100 bg-emerald-50/40 cursor-pointer">
              <div class="flex items-center gap-3">
                <i class="bi bi-whatsapp text-2xl text-emerald-600"></i>
                <div>
                  <span class="text-sm font-bold text-emerald-950 block">WhatsApp</span>
                  <span class="text-[11px] text-emerald-700">Instant Messages</span>
                </div>
              </div>
              <input type="checkbox" v-model="form.whatsapp_enabled" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 h-5 w-5" />
            </label>

            <!-- SMS Switch -->
            <label class="flex items-center justify-between p-4 rounded-xl border border-blue-100 bg-blue-50/40 cursor-pointer">
              <div class="flex items-center gap-3">
                <i class="bi bi-chat-text text-2xl text-blue-600"></i>
                <div>
                  <span class="text-sm font-bold text-blue-950 block">SMS</span>
                  <span class="text-[11px] text-blue-700">Transactional SMS</span>
                </div>
              </div>
              <input type="checkbox" v-model="form.sms_enabled" class="rounded border-blue-300 text-blue-600 focus:ring-blue-500 h-5 w-5" />
            </label>

            <!-- Email Switch -->
            <label class="flex items-center justify-between p-4 rounded-xl border border-purple-100 bg-purple-50/40 cursor-pointer">
              <div class="flex items-center gap-3">
                <i class="bi bi-envelope text-2xl text-purple-600"></i>
                <div>
                  <span class="text-sm font-bold text-purple-950 block">Email</span>
                  <span class="text-[11px] text-purple-700">SMTP Emails</span>
                </div>
              </div>
              <input type="checkbox" v-model="form.email_enabled" class="rounded border-purple-300 text-purple-600 focus:ring-purple-500 h-5 w-5" />
            </label>

            <!-- Unified In-App & Web Push Switch -->
            <label class="flex items-center justify-between p-4 rounded-xl border border-indigo-100 bg-indigo-50/40 cursor-pointer">
              <div class="flex items-center gap-3">
                <i class="bi bi-bell text-2xl text-indigo-600"></i>
                <div>
                  <span class="text-sm font-bold text-indigo-950 block">In-App &amp; Web Push</span>
                  <span class="text-[11px] text-indigo-700">Navbar Bell &amp; Browser Push Alerts</span>
                </div>
              </div>
              <input type="checkbox" v-model="form.in_app_enabled" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500 h-5 w-5" />
            </label>
          </div>
        </div>

        <!-- WhatsApp Provider Choice Architecture -->
        <div v-if="form.whatsapp_enabled" class="bg-white border border-gray-200/80 rounded-2xl p-6 shadow-sm space-y-5">
          <div class="border-b border-gray-100 pb-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <h2 class="text-base font-bold text-gray-900 flex items-center gap-2">
                <i class="bi bi-whatsapp text-emerald-600 text-xl"></i>
                <span>WhatsApp Connection Provider Architecture</span>
              </h2>
              <p class="text-xs text-gray-500 mt-0.5">Select how your store connects to WhatsApp (Personal WhatsApp Web QR Scanner vs API Gateway)</p>
            </div>
            <span class="text-[10px] font-bold px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 uppercase tracking-wider self-start sm:self-auto">
              Provider: {{ form.whatsapp_provider }}
            </span>
          </div>

          <!-- Provider Radio Selection Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Option 1: Web WhatsApp QR Scanner -->
            <label
              :class="[
                'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all',
                form.whatsapp_provider === 'whatsapp_web'
                  ? 'border-emerald-600 bg-emerald-50/30 shadow-sm'
                  : 'border-gray-200 bg-white hover:border-gray-300'
              ]"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <input type="radio" v-model="form.whatsapp_provider" value="whatsapp_web" class="text-emerald-600 focus:ring-emerald-600 h-4 w-4" />
                  <span class="text-xs font-bold text-gray-900">Web WhatsApp QR Scanner</span>
                </div>
                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">Scan QR</span>
              </div>
              <p class="text-[11px] text-gray-500 leading-relaxed">Link your store phone number directly by scanning the Web QR Code. Zero monthly per-message fees.</p>
            </label>

            <!-- Option 2: Gateway API -->
            <label
              :class="[
                'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all',
                form.whatsapp_provider === 'gateway_api'
                  ? 'border-emerald-600 bg-emerald-50/30 shadow-sm'
                  : 'border-gray-200 bg-white hover:border-gray-300'
              ]"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <input type="radio" v-model="form.whatsapp_provider" value="gateway_api" class="text-emerald-600 focus:ring-emerald-600 h-4 w-4" />
                  <span class="text-xs font-bold text-gray-900">WhatsApp Gateway API</span>
                </div>
                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded bg-blue-100 text-blue-800">HTTP API</span>
              </div>
              <p class="text-[11px] text-gray-500 leading-relaxed">Connect via external WhatsApp HTTP API Gateway (API URL, API Key &amp; Instance Name).</p>
            </label>

            <!-- Option 3: Meta Cloud API -->
            <label
              :class="[
                'relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition-all',
                form.whatsapp_provider === 'meta_cloud'
                  ? 'border-emerald-600 bg-emerald-50/30 shadow-sm'
                  : 'border-gray-200 bg-white hover:border-gray-300'
              ]"
            >
              <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                  <input type="radio" v-model="form.whatsapp_provider" value="meta_cloud" class="text-emerald-600 focus:ring-emerald-600 h-4 w-4" />
                  <span class="text-xs font-bold text-gray-900">Meta Cloud API (Official)</span>
                </div>
                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded bg-purple-100 text-purple-800">Official WABA</span>
              </div>
              <p class="text-[11px] text-gray-500 leading-relaxed">Official WhatsApp Business API hosted by Meta Graph API with Phone Number ID &amp; Token.</p>
            </label>
          </div>

          <!-- Provider A: Web WhatsApp QR Code Live Card -->
          <div v-if="form.whatsapp_provider === 'whatsapp_web'" class="bg-gradient-to-br from-[#1e1b4b] via-[#0f172a] to-[#1e1b4b] p-6 rounded-2xl text-white space-y-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <i class="bi bi-qr-code-scan text-xl text-emerald-400"></i>
                <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-200">Store WhatsApp Session Scanner</h3>
              </div>
              <button
                type="button"
                @click="fetchQrCode"
                :disabled="qrLoading"
                class="text-xs font-bold bg-white/10 hover:bg-white/20 text-white px-3 py-1.5 rounded-xl transition flex items-center gap-1.5"
              >
                <i :class="['bi bi-arrow-clockwise', qrLoading ? 'animate-spin' : '']"></i>
                <span>{{ qrLoading ? 'Checking...' : 'Refresh Session' }}</span>
              </button>
            </div>

            <div class="flex flex-col md:flex-row items-center gap-6 pt-2">
              <!-- Live QR Image Container -->
              <div class="w-44 h-44 bg-white rounded-2xl p-2 flex flex-col items-center justify-center border border-indigo-200/30 shadow-2xl text-gray-800 shrink-0">
                <div v-if="qrLoading" class="text-center space-y-2">
                  <div class="animate-spin h-7 w-7 border-3 border-emerald-600 border-t-transparent rounded-full mx-auto"></div>
                  <span class="text-[11px] font-bold text-gray-500 block">Connecting...</span>
                </div>
                
                <div v-else-if="qrData?.is_connected" class="text-center p-3">
                  <div class="text-4xl mb-1 text-emerald-500"><i class="bi bi-check-circle-fill"></i></div>
                  <span class="text-xs font-bold text-emerald-700 block">CONNECTED</span>
                  <span class="text-[10px] text-gray-500">Store Session Active</span>
                </div>

                <div v-else-if="qrData?.qr_image" class="text-center">
                  <img :src="qrData.qr_image" alt="WhatsApp Web QR Code" class="w-40 h-40 rounded-lg shadow-sm" />
                </div>

                <div v-else class="text-center p-3 text-gray-400">
                  <i class="bi bi-qr-code text-3xl mb-1 block"></i>
                  <span class="text-[10px] font-medium leading-tight block">{{ qrData?.message || 'Web Gateway Standby' }}</span>
                </div>
              </div>

              <!-- Scanner Instructions -->
              <div class="space-y-3 text-xs text-indigo-100 flex-1">
                <div class="flex items-center gap-2">
                  <span :class="['inline-block w-2.5 h-2.5 rounded-full', qrData?.is_connected ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400']"></span>
                  <span class="font-bold text-white uppercase text-[11px] tracking-wider">
                    Session Status: {{ qrData?.is_connected ? 'CONNECTED & READY' : (qrData?.status || 'SCAN REQUIRED') }}
                  </span>
                </div>

                <ol class="list-decimal list-inside space-y-2 text-xs text-indigo-200">
                  <li>Open <strong>WhatsApp</strong> on your store mobile phone.</li>
                  <li>Tap <strong>Menu</strong> or <strong>Settings</strong> &rarr; <strong>Linked Devices</strong>.</li>
                  <li>Tap <strong>Link a Device</strong> and point your camera at the QR code on the left.</li>
                  <li>Once scanned, all automated Sales &amp; Payment receipts will be sent from your store's WhatsApp number!</li>
                </ol>
              </div>
            </div>
          </div>

          <!-- Provider B: Gateway API Fields -->
          <div v-if="form.whatsapp_provider === 'gateway_api'" class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">API Base URL</label>
              <input type="text" v-model="form.whatsapp_api_url" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500" placeholder="https://wapi.hspsms.com/public/wa/api/send" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">API Token / Key</label>
              <input type="text" v-model="form.whatsapp_api_key" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500" placeholder="API Key" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">App Instance Name</label>
              <input type="text" v-model="form.whatsapp_app_name" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500" placeholder="Instance Name" />
            </div>
          </div>

          <!-- Provider C: Meta Cloud API Fields -->
          <div v-if="form.whatsapp_provider === 'meta_cloud'" class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-purple-50/50 p-4 rounded-xl border border-purple-100">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Meta Phone Number ID *</label>
              <input type="text" v-model="form.meta_whatsapp_phone_number_id" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-500" placeholder="e.g. 10928374659102" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Meta Permanent Access Token *</label>
              <input type="text" v-model="form.meta_whatsapp_access_token" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm font-mono focus:ring-2 focus:ring-purple-500" placeholder="EAAG..." />
            </div>
          </div>

          <!-- WhatsApp Tester Card -->
          <div class="border-t border-gray-100 pt-4 mt-2">
            <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider mb-3">Test WhatsApp Dispatch</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <input type="text" v-model="testPhone" placeholder="Mobile No (e.g. 9876543210)" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500" />
              </div>
              <div class="sm:col-span-2 flex gap-2">
                <input type="text" v-model="testMessage" placeholder="Test Message Content" class="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500" />
                <button
                  type="button"
                  @click="sendTestWhatsApp"
                  :disabled="testLoading"
                  class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-4 py-2 rounded-xl shadow-sm transition shrink-0 flex items-center gap-1.5"
                >
                  <i class="bi bi-send-fill"></i>
                  <span>{{ testLoading ? 'Sending...' : 'Send Test' }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- SMS Gateway Credentials -->
        <div v-if="form.sms_enabled" class="bg-white border border-gray-200/80 rounded-2xl p-6 shadow-sm space-y-4">
          <div class="flex items-center gap-2">
            <i class="bi bi-chat-left-text text-blue-600 text-xl"></i>
            <h2 class="text-base font-bold text-gray-900">SMS Gateway Credentials</h2>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">SMS Gateway URL</label>
              <input type="text" v-model="form.sms_api_url" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="https://api.sms-gateway.com/send" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">SMS API Key</label>
              <input type="text" v-model="form.sms_api_key" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="Your SMS API Key" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Sender Name / Header ID</label>
              <input type="text" v-model="form.sms_sender_name" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500" placeholder="e.g. STOREID" />
            </div>
          </div>
        </div>

        <!-- FCM Firebase Web Push Credentials -->
        <div v-if="form.in_app_enabled" class="bg-white border border-gray-200/80 rounded-2xl p-6 shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <i class="bi bi-phone-vibrate text-rose-600 text-xl"></i>
              <h2 class="text-base font-bold text-gray-900">Firebase Service Account JSON Credentials (FCM v1)</h2>
            </div>
            <span class="text-[11px] font-mono text-gray-400">storage/app/firebase-credentials.json</span>
          </div>

          <div class="space-y-2 pt-1">
            <label class="block text-xs font-semibold text-gray-600">Service Account Key JSON Content</label>
            <p class="text-[11px] text-gray-500">Paste your Firebase Service Account JSON file content below, or place <code>firebase-credentials.json</code> directly in <code>storage/app/</code>.</p>
            <textarea v-model="form.firebase_credentials_json" rows="5" class="w-full border border-gray-300 rounded-xl p-3 text-xs font-mono focus:ring-2 focus:ring-rose-500" placeholder="{ &quot;type&quot;: &quot;service_account&quot;, &quot;project_id&quot;: &quot;...&quot;, &quot;private_key_id&quot;: &quot;...&quot; }"></textarea>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
          <button
            type="submit"
            :disabled="form.processing"
            class="bg-[#2e2c92] hover:bg-[#201d70] text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-md transition disabled:opacity-50 flex items-center gap-2"
          >
            <i class="bi bi-check-circle"></i>
            <span>{{ form.processing ? 'Saving...' : 'Save Gateway Settings' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AuthenticatedLayout>
</template>
