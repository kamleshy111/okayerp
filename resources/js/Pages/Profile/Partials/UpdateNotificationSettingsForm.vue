<script setup>
import { ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();
const user = page.props.auth.user;

const form = useForm({
    auto_whatsapp_reminders_enabled: !!user.auto_whatsapp_reminders_enabled,
    auto_whatsapp_30_frequency: user.auto_whatsapp_30_frequency || 'weekly',
    auto_whatsapp_60_frequency: user.auto_whatsapp_60_frequency || 'twice_a_week',
    auto_whatsapp_90_frequency: user.auto_whatsapp_90_frequency || 'three_times_a_week',
    whatsapp_api_url: user.whatsapp_api_url || 'https://wapi.hspsms.com/public/wa/api/send',
    whatsapp_api_key: user.whatsapp_api_key || '30dce73d773a4ceaa7b35c369e4b5b43',
    whatsapp_app_name: user.whatsapp_app_name || 'sarpanchsangh',
    whatsapp_message_template: user.whatsapp_message_template || 'Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Please find your account statement link below. Thank you!',
    
    auto_sms_reminders_enabled: !!user.auto_sms_reminders_enabled,
    auto_sms_30_frequency: user.auto_sms_30_frequency || 'weekly',
    auto_sms_60_frequency: user.auto_sms_60_frequency || 'twice_a_week',
    auto_sms_90_frequency: user.auto_sms_90_frequency || 'three_times_a_week',
    sms_api_url: user.sms_api_url || 'http://sms.hspsms.com/sendSMS',
    sms_api_key: user.sms_api_key || '30dce73d773a4ceaa7b35c369e4b5b43',
    sms_sender_name: user.sms_sender_name || 'SARPCH',
    sms_message_template: user.sms_message_template || 'Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Please find your account statement link: {pdf_url} Thank you!',
});

const updateReminders = () => {
    form.patch(route('profile.update-reminders'), {
        preserveScroll: true,
        onSuccess: () => {
            // Configuration updated successfully
        }
    });
};
</script>

<template>
    <section class="max-w-none mx-auto p-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900 font-bold">
                Automatic Aging Reminders Configuration
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Configure your store's automatic customer aging reminders via WhatsApp and SMS channels.
            </p>
        </header>

        <form @submit.prevent="updateReminders" class="mt-6 space-y-8">
            <div class="grid grid-cols-1 gap-8">
                
                <!-- WhatsApp Notifications -->
                <div class="bg-emerald-50/50 p-6 rounded-xl border border-emerald-100 space-y-4">
                    <div class="flex items-center">
                        <input
                            id="auto_whatsapp_reminders_enabled"
                            type="checkbox"
                            class="rounded border-emerald-300 text-emerald-600 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 h-4 w-4"
                            v-model="form.auto_whatsapp_reminders_enabled"
                        />
                        <label for="auto_whatsapp_reminders_enabled" class="ml-2 block text-sm font-semibold text-emerald-900">
                            Enable Automatic WhatsApp Outstanding Reminders
                        </label>
                        <InputError class="mt-2" :message="form.errors.auto_whatsapp_reminders_enabled" />
                    </div>

                    <div v-if="form.auto_whatsapp_reminders_enabled" class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                        <div>
                            <InputLabel for="auto_whatsapp_30_frequency" value="30 Days Bracket Frequency" />
                            <select
                                id="auto_whatsapp_30_frequency"
                                class="mt-1 block w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm bg-white text-black p-2"
                                v-model="form.auto_whatsapp_30_frequency"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="once_a_month">1 Time in Month</option>
                                <option value="twice_a_month">2 Times in Month</option>
                                <option value="weekly">Weekly (1 Time in Week)</option>
                                <option value="twice_a_week">2 Times in Week</option>
                                <option value="three_times_a_week">3 Times in Week</option>
                                <option value="daily">Daily</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.auto_whatsapp_30_frequency" />
                        </div>

                        <div>
                            <InputLabel for="auto_whatsapp_60_frequency" value="60 Days Bracket Frequency" />
                            <select
                                id="auto_whatsapp_60_frequency"
                                class="mt-1 block w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm bg-white text-black p-2"
                                v-model="form.auto_whatsapp_60_frequency"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="once_a_month">1 Time in Month</option>
                                <option value="twice_a_month">2 Times in Month</option>
                                <option value="weekly">Weekly (1 Time in Week)</option>
                                <option value="twice_a_week">2 Times in Week</option>
                                <option value="three_times_a_week">3 Times in Week</option>
                                <option value="daily">Daily</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.auto_whatsapp_60_frequency" />
                        </div>

                        <div>
                            <InputLabel for="auto_whatsapp_90_frequency" value="90+ Days Bracket Frequency" />
                            <select
                                id="auto_whatsapp_90_frequency"
                                class="mt-1 block w-full border-gray-300 focus:border-emerald-500 focus:ring-emerald-500 rounded-md shadow-sm bg-white text-black p-2"
                                v-model="form.auto_whatsapp_90_frequency"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="once_a_month">1 Time in Month</option>
                                <option value="twice_a_month">2 Times in Month</option>
                                <option value="weekly">Weekly (1 Time in Week)</option>
                                <option value="twice_a_week">2 Times in Week</option>
                                <option value="three_times_a_week">3 Times in Week</option>
                                <option value="daily">Daily</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.auto_whatsapp_90_frequency" />
                        </div>
                    </div>

                    <!-- WhatsApp API Credentials Settings -->
                    <div v-if="form.auto_whatsapp_reminders_enabled" class="grid grid-cols-1 md:grid-cols-3 gap-6 border-t border-emerald-100 pt-4 mt-2">
                        <div>
                            <InputLabel for="whatsapp_api_url" value="WhatsApp API Base URL" />
                            <TextInput
                                id="whatsapp_api_url"
                                type="text"
                                class="mt-1 block w-full border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500"
                                v-model="form.whatsapp_api_url"
                                placeholder="https://wapi.hspsms.com/public/wa/api/send"
                            />
                            <InputError class="mt-2" :message="form.errors.whatsapp_api_url" />
                        </div>

                        <div>
                            <InputLabel for="whatsapp_api_key" value="WhatsApp API Token / Key" />
                            <TextInput
                                id="whatsapp_api_key"
                                type="text"
                                class="mt-1 block w-full border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500"
                                v-model="form.whatsapp_api_key"
                                placeholder="API Key"
                            />
                            <InputError class="mt-2" :message="form.errors.whatsapp_api_key" />
                        </div>

                        <div>
                            <InputLabel for="whatsapp_app_name" value="WhatsApp Campaign / App Name" />
                            <TextInput
                                id="whatsapp_app_name"
                                type="text"
                                class="mt-1 block w-full border-emerald-300 focus:border-emerald-500 focus:ring-emerald-500"
                                v-model="form.whatsapp_app_name"
                                placeholder="sarpanchsangh"
                            />
                            <InputError class="mt-2" :message="form.errors.whatsapp_app_name" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel for="whatsapp_message_template" value="WhatsApp Message Template" />
                            <textarea
                                id="whatsapp_message_template"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 text-black p-2.5"
                                v-model="form.whatsapp_message_template"
                                placeholder="Enter template message..."
                            ></textarea>
                            <p class="mt-1.5 text-xs text-emerald-800">
                                Available tags: <code class="bg-emerald-100 px-1 py-0.5 rounded font-bold">{customer_name}</code>, <code class="bg-emerald-100 px-1 py-0.5 rounded font-bold">{amount}</code>, <code class="bg-emerald-100 px-1 py-0.5 rounded font-bold">{business_name}</code>, <code class="bg-emerald-100 px-1 py-0.5 rounded font-bold">{pdf_url}</code>.
                            </p>
                            <InputError class="mt-2" :message="form.errors.whatsapp_message_template" />
                        </div>
                    </div>
                </div>

                <!-- SMS Notifications -->
                <div class="bg-blue-50/50 p-6 rounded-xl border border-blue-100 space-y-4">
                    <div class="flex items-center">
                        <input
                            id="auto_sms_reminders_enabled"
                            type="checkbox"
                            class="rounded border-blue-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 h-4 w-4"
                            v-model="form.auto_sms_reminders_enabled"
                        />
                        <label for="auto_sms_reminders_enabled" class="ml-2 block text-sm font-semibold text-blue-900">
                            Enable Automatic SMS Outstanding Reminders
                        </label>
                        <InputError class="mt-2" :message="form.errors.auto_sms_reminders_enabled" />
                    </div>

                    <div v-if="form.auto_sms_reminders_enabled" class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
                        <div>
                            <InputLabel for="auto_sms_30_frequency" value="30 Days Bracket Frequency" />
                            <select
                                id="auto_sms_30_frequency"
                                class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm bg-white text-black p-2"
                                v-model="form.auto_sms_30_frequency"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="once_a_month">1 Time in Month</option>
                                <option value="twice_a_month">2 Times in Month</option>
                                <option value="weekly">Weekly (1 Time in Week)</option>
                                <option value="twice_a_week">2 Times in Week</option>
                                <option value="three_times_a_week">3 Times in Week</option>
                                <option value="daily">Daily</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.auto_sms_30_frequency" />
                        </div>

                        <div>
                            <InputLabel for="auto_sms_60_frequency" value="60 Days Bracket Frequency" />
                            <select
                                id="auto_sms_60_frequency"
                                class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm bg-white text-black p-2"
                                v-model="form.auto_sms_60_frequency"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="once_a_month">1 Time in Month</option>
                                <option value="twice_a_month">2 Times in Month</option>
                                <option value="weekly">Weekly (1 Time in Week)</option>
                                <option value="twice_a_week">2 Times in Week</option>
                                <option value="three_times_a_week">3 Times in Week</option>
                                <option value="daily">Daily</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.auto_sms_60_frequency" />
                        </div>

                        <div>
                            <InputLabel for="auto_sms_90_frequency" value="90+ Days Bracket Frequency" />
                            <select
                                id="auto_sms_90_frequency"
                                class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm bg-white text-black p-2"
                                v-model="form.auto_sms_90_frequency"
                            >
                                <option value="disabled">Disabled</option>
                                <option value="once_a_month">1 Time in Month</option>
                                <option value="twice_a_month">2 Times in Month</option>
                                <option value="weekly">Weekly (1 Time in Week)</option>
                                <option value="twice_a_week">2 Times in Week</option>
                                <option value="three_times_a_week">3 Times in Week</option>
                                <option value="daily">Daily</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.auto_sms_90_frequency" />
                        </div>
                    </div>

                    <!-- SMS API Credentials Settings -->
                    <div v-if="form.auto_sms_reminders_enabled" class="grid grid-cols-1 md:grid-cols-3 gap-6 border-t border-blue-100 pt-4 mt-2">
                        <div>
                            <InputLabel for="sms_api_url" value="SMS API Base URL" />
                            <TextInput
                                id="sms_api_url"
                                type="text"
                                class="mt-1 block w-full border-blue-300 focus:border-blue-500 focus:ring-blue-500"
                                v-model="form.sms_api_url"
                                placeholder="http://sms.hspsms.com/sendSMS"
                            />
                            <InputError class="mt-2" :message="form.errors.sms_api_url" />
                        </div>

                        <div>
                            <InputLabel for="sms_api_key" value="SMS API Token / Key" />
                            <TextInput
                                id="sms_api_key"
                                type="text"
                                class="mt-1 block w-full border-blue-300 focus:border-blue-500 focus:ring-blue-500"
                                v-model="form.sms_api_key"
                                placeholder="API Key"
                            />
                            <InputError class="mt-2" :message="form.errors.sms_api_key" />
                        </div>

                        <div>
                            <InputLabel for="sms_sender_name" value="SMS Sender Name" />
                            <TextInput
                                id="sms_sender_name"
                                type="text"
                                class="mt-1 block w-full border-blue-300 focus:border-blue-500 focus:ring-blue-500"
                                v-model="form.sms_sender_name"
                                placeholder="SARPCH"
                            />
                            <InputError class="mt-2" :message="form.errors.sms_sender_name" />
                        </div>

                        <div class="md:col-span-3">
                            <InputLabel for="sms_message_template" value="SMS Message Template" />
                            <textarea
                                id="sms_message_template"
                                rows="3"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-black p-2.5"
                                v-model="form.sms_message_template"
                                placeholder="Enter template message..."
                            ></textarea>
                            <p class="mt-1.5 text-xs text-blue-800">
                                Available tags: <code class="bg-blue-100 px-1 py-0.5 rounded font-bold">{customer_name}</code>, <code class="bg-blue-100 px-1 py-0.5 rounded font-bold">{amount}</code>, <code class="bg-blue-100 px-1 py-0.5 rounded font-bold">{business_name}</code>, <code class="bg-blue-100 px-1 py-0.5 rounded font-bold">{pdf_url}</code>.
                            </p>
                            <InputError class="mt-2" :message="form.errors.sms_message_template" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
