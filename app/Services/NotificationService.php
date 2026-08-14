<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\NotificationTrigger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Ensure default settings, templates, and trigger matrix exist for the user.
     */
    public static function ensureDefaults(User $user): array
    {
        // 1. Settings
        $setting = NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'whatsapp_enabled' => (bool)($user->auto_whatsapp_reminders_enabled ?? true),
                'whatsapp_api_url' => $user->whatsapp_api_url ?: null,
                'whatsapp_api_key' => $user->whatsapp_api_key ?: null,
                'whatsapp_app_name' => $user->whatsapp_app_name ?: null,
                'sms_enabled' => (bool)($user->auto_sms_reminders_enabled ?? false),
                'sms_api_url' => $user->sms_api_url ?: null,
                'sms_api_key' => $user->sms_api_key ?: null,
                'sms_sender_name' => $user->sms_sender_name ?: null,
                'email_enabled' => false,
                'in_app_enabled' => true,
            ]
        );

        // 2. Templates
        $defaultTemplates = [
            'aging_30' => [
                'name' => '30 Days Outstanding Due Reminder',
                'email_subject' => 'Payment Due Reminder — 30 Days Outstanding',
                'email_body' => 'Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Statement: {pdf_url}',
                'whatsapp_body' => $user->whatsapp_message_template ?: 'Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Please find your account statement link below. Thank you!',
                'sms_body' => $user->sms_message_template ?: 'Dear {customer_name}, you have an outstanding balance of ₹{amount} with {business_name}. Statement link: {pdf_url}',
            ],
            'aging_60' => [
                'name' => '60 Days Outstanding Due Reminder',
                'email_subject' => 'Payment Due Reminder — 60 Days Outstanding',
                'email_body' => 'Dear {customer_name}, urgent reminder for your outstanding balance of ₹{amount} with {business_name}. Statement: {pdf_url}',
                'whatsapp_body' => 'Dear {customer_name}, urgent payment reminder! Your account has an overdue balance of ₹{amount} (60+ days) with {business_name}. Statement link: {pdf_url}',
                'sms_body' => 'Urgent: {customer_name}, overdue balance of ₹{amount} with {business_name}. Statement: {pdf_url}',
            ],
            'aging_90' => [
                'name' => '90+ Days Outstanding Critical Due Reminder',
                'email_subject' => 'CRITICAL Overdue Notice — 90+ Days',
                'email_body' => 'Dear {customer_name}, critical payment notice for ₹{amount} overdue by 90+ days with {business_name}. Please clear immediately. Statement: {pdf_url}',
                'whatsapp_body' => 'CRITICAL NOTICE: Dear {customer_name}, your balance of ₹{amount} is critically overdue (90+ days) at {business_name}. Please clear it immediately. Statement link: {pdf_url}',
                'sms_body' => 'CRITICAL: {customer_name}, balance ₹{amount} is 90+ days overdue at {business_name}. Statement: {pdf_url}',
            ],
            'sale_created' => [
                'name' => 'Sale Invoice Created',
                'email_subject' => 'Invoice {invoice_no} from {business_name}',
                'email_body' => 'Dear {customer_name}, thank you for your order! Invoice #{invoice_no} for ₹{amount} has been generated on {date}. View: {pdf_url}',
                'whatsapp_body' => 'Dear {customer_name}, thank you for shopping with {business_name}! Invoice #{invoice_no} of ₹{amount} is ready. View invoice: {pdf_url}',
                'sms_body' => 'Thank you {customer_name}! Invoice #{invoice_no} of ₹{amount} generated at {business_name}. View: {pdf_url}',
            ],
            'purchase_created' => [
                'name' => 'Purchase Order Created',
                'email_subject' => 'Purchase Order {invoice_no} created',
                'email_body' => 'Purchase order #{invoice_no} of ₹{amount} placed with {supplier_name} on {date}.',
                'whatsapp_body' => 'Purchase Order #{invoice_no} of ₹{amount} created for {supplier_name} at {business_name}.',
                'sms_body' => 'PO #{invoice_no} of ₹{amount} created for {supplier_name}.',
            ],
            'customer_payment' => [
                'name' => 'Customer Payment Received Receipt',
                'email_subject' => 'Payment Receipt for {customer_name}',
                'email_body' => 'Dear {customer_name}, payment of ₹{amount} received via {payment_method} on {date}. Thank you!',
                'whatsapp_body' => 'Dear {customer_name}, payment of ₹{amount} received via {payment_method} at {business_name}. Remaining due: ₹{remaining_due}. Thank you!',
                'sms_body' => 'Payment of ₹{amount} received at {business_name}. Thank you {customer_name}!',
            ],
            'supplier_payment' => [
                'name' => 'Supplier Payment Paid Receipt',
                'email_subject' => 'Payment Voucher for {supplier_name}',
                'email_body' => 'Payment of ₹{amount} paid to {supplier_name} via {payment_method} on {date}.',
                'whatsapp_body' => 'Payment of ₹{amount} paid to {supplier_name} via {payment_method} by {business_name}.',
                'sms_body' => 'Payment of ₹{amount} paid to {supplier_name}.',
            ],
            'low_stock_alert' => [
                'name' => 'Low Stock Warning Alert',
                'email_subject' => 'Low Stock Alert: {product_name}',
                'email_body' => 'Alert! Product {product_name} is running low (Current Qty: {stock_qty}). Please reorder.',
                'whatsapp_body' => '⚠️ Low Stock Alert at {business_name}: {product_name} stock is down to {stock_qty} unit(s).',
                'sms_body' => 'Low Stock Alert: {product_name} qty is {stock_qty}. Reorder needed.',
            ],
        ];

        foreach ($defaultTemplates as $key => $tmpl) {
            NotificationTemplate::firstOrCreate(
                ['user_id' => $user->id, 'key' => $key],
                [
                    'name' => $tmpl['name'],
                    'email_subject' => $tmpl['email_subject'],
                    'email_body' => $tmpl['email_body'],
                    'whatsapp_body' => $tmpl['whatsapp_body'],
                    'sms_body' => $tmpl['sms_body'],
                    'is_active' => true,
                ]
            );
        }

        // 3. Triggers Matrix
        $defaultTriggers = [
            'aging_30' => ['event_name' => '30 Days Aging Reminder', 'frequency' => $user->auto_whatsapp_30_frequency ?: 'weekly'],
            'aging_60' => ['event_name' => '60 Days Aging Reminder', 'frequency' => $user->auto_whatsapp_60_frequency ?: 'twice_a_week'],
            'aging_90' => ['event_name' => '90+ Days Critical Reminder', 'frequency' => $user->auto_whatsapp_90_frequency ?: 'three_times_a_week'],
            'sale_created' => ['event_name' => 'Sale Invoice Created', 'frequency' => 'instant'],
            'purchase_created' => ['event_name' => 'Purchase Order Created', 'frequency' => 'instant'],
            'customer_payment' => ['event_name' => 'Customer Payment Received', 'frequency' => 'instant'],
            'supplier_payment' => ['event_name' => 'Supplier Payment Paid', 'frequency' => 'instant'],
            'low_stock_alert' => ['event_name' => 'Low Stock Warning', 'frequency' => 'instant'],
        ];

        foreach ($defaultTriggers as $key => $trig) {
            NotificationTrigger::firstOrCreate(
                ['user_id' => $user->id, 'event_key' => $key],
                [
                    'event_name' => $trig['event_name'],
                    'frequency' => $trig['frequency'],
                    'whatsapp_enabled' => true,
                    'sms_enabled' => false,
                    'email_enabled' => false,
                    'in_app_enabled' => true,
                ]
            );
        }

        return [
            'settings' => $setting,
            'templates' => NotificationTemplate::where('user_id', $user->id)->get(),
            'triggers' => NotificationTrigger::where('user_id', $user->id)->get(),
        ];
    }

    /**
     * Compile text with variable replacements.
     */
    public static function compile(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', (string)$value, $text);
        }
        return $text;
    }

    /**
     * Dispatch event notification asynchronously via Queue Job.
     */
    public function dispatch(User $user, string $eventKey, array $variables = [], ?string $recipientPhone = null, ?string $recipientEmail = null, ?string $actionUrl = null): void
    {
        \App\Jobs\SendNotificationJob::dispatch(
            $user->id,
            $eventKey,
            $variables,
            $recipientPhone,
            $recipientEmail,
            $actionUrl
        );
    }

    /**
     * Dispatch event notification inline across enabled channels.
     */
    public function dispatchInline(User $user, string $eventKey, array $variables = [], ?string $recipientPhone = null, ?string $recipientEmail = null, ?string $actionUrl = null): array
    {
        self::ensureDefaults($user);
        $setting = NotificationSetting::where('user_id', $user->id)->first();
        $template = NotificationTemplate::where('user_id', $user->id)->where('key', $eventKey)->first();
        $trigger = NotificationTrigger::where('user_id', $user->id)->where('event_key', $eventKey)->first();

        if (!$template || !$template->is_active || ($trigger && $trigger->frequency === 'disabled')) {
            return ['status' => 'skipped', 'message' => 'Trigger or template is disabled.'];
        }

        $businessName = $user->name ?: 'OkayERP';
        $variables['business_name'] = $variables['business_name'] ?? $businessName;
        $results = [];

        // 1. In-App Bell & FCM Push — Store Internal Alerts Only (e.g. Low Stock Warning)
        $storeInternalEvents = ['low_stock_alert'];

        if (in_array($eventKey, $storeInternalEvents) && ($trigger ? $trigger->in_app_enabled : true) && ($setting ? $setting->in_app_enabled : true)) {
            $title = $template->name;
            $message = self::compile($template->whatsapp_body ?: $template->email_subject ?: 'Notification', $variables);

            Notification::create([
                'user_id' => $user->id,
                'type' => 'warning',
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'icon' => 'bi-exclamation-triangle',
                'is_read' => false,
            ]);

            NotificationLog::create([
                'user_id' => $user->id,
                'channel' => 'in_app',
                'event_key' => $eventKey,
                'recipient' => $user->email,
                'subject' => $title,
                'body' => $message,
                'status' => 'sent',
                'sent_at' => Carbon::now(),
            ]);

            // Dispatch FCM Web Push for Store Owner
            try {
                $fcmTokens = \App\Models\UserFcmToken::where('user_id', $user->id)->pluck('fcm_token')->toArray();
                if (!empty($fcmTokens)) {
                    (new PushNotificationService())->send($fcmTokens, $title, $message, array_merge($variables, ['event_key' => $eventKey, 'action_url' => $actionUrl]));
                }
            } catch (\Exception $fe) {
                Log::error("FCM Web Push dispatch error: " . $fe->getMessage());
            }

            $results['in_app'] = 'sent';
        }

        // 2. WhatsApp Notification
        if ($recipientPhone && ($trigger ? $trigger->whatsapp_enabled : true) && ($setting ? $setting->whatsapp_enabled : true) && $template->whatsapp_body) {
            $waMessage = self::compile($template->whatsapp_body, $variables);
            $cleanPhone = preg_replace('/[^0-9]/', '', $recipientPhone);
            if (strlen($cleanPhone) === 10) {
                $cleanPhone = '91' . $cleanPhone;
            }

            try {
                $provider = $setting->whatsapp_provider ?? 'whatsapp_web';
                $status = 'failed';
                $errorMsg = null;

                if ($provider === 'whatsapp_web') {
                    // Driver A: Web WhatsApp Scanner Node Gateway
                    $nodeUrl = config('services.whatsapp.node_url', 'http://localhost:3000/send-message');
                    $secretToken = config('services.whatsapp.node_secret_token', 'master_secret_token_2026');
                    $storeCode = 'store_' . $user->id;

                    if (str_contains($nodeUrl, 'localhost') || str_contains($nodeUrl, '127.0.0.1')) {
                        $hostIp = @gethostbyname('host.docker.internal');
                        if (!$hostIp || $hostIp === 'host.docker.internal') {
                            $hostIp = trim((string) @shell_exec("ip route show default 2>/dev/null | awk '{print $3}'")) ?: null;
                        }
                        if ($hostIp) {
                            $nodeUrl = str_replace(['localhost', '127.0.0.1'], $hostIp, $nodeUrl);
                        }
                    }

                    $timestamp = time();
                    $signature = hash_hmac('sha256', "{$timestamp}.{$storeCode}", $secretToken);

                    $response = Http::timeout(10)->withHeaders([
                        'X-KR-Timestamp' => $timestamp,
                        'X-KR-Signature' => $signature,
                        'Authorization'  => 'Bearer ' . $secretToken,
                    ])->post($nodeUrl, [
                        'school_code' => $storeCode,
                        'store_code'  => $storeCode,
                        'number'      => $cleanPhone,
                        'message'     => $waMessage,
                    ]);

                    $status = $response->successful() ? 'sent' : 'failed';
                    $errorMsg = $response->successful() ? null : $response->body();
                } elseif ($provider === 'meta_cloud') {
                    // Driver B: Meta Cloud API
                    $phoneId = $setting->meta_whatsapp_phone_number_id;
                    $accessToken = $setting->meta_whatsapp_access_token;
                    if ($phoneId && $accessToken) {
                        $metaUrl = "https://graph.facebook.com/v18.0/{$phoneId}/messages";
                        $response = Http::timeout(10)->withToken($accessToken)->post($metaUrl, [
                            'messaging_product' => 'whatsapp',
                            'to' => $cleanPhone,
                            'type' => 'text',
                            'text' => ['body' => $waMessage],
                        ]);
                        $status = $response->successful() ? 'sent' : 'failed';
                        $errorMsg = $response->successful() ? null : $response->body();
                    } else {
                        $errorMsg = 'Meta Cloud API Credentials missing.';
                    }
                } else {
                    // Driver C: Standard HTTP API Gateway
                    $apiUrl = $setting->whatsapp_api_url;
                    $apiKey = $setting->whatsapp_api_key;
                    $appName = $setting->whatsapp_app_name;

                    if ($apiUrl && $apiKey) {
                        $response = Http::timeout(10)->get($apiUrl, [
                            'app_name' => $appName,
                            'api_key' => $apiKey,
                            'mobile' => $cleanPhone,
                            'message' => $waMessage,
                        ]);

                        $status = $response->successful() ? 'sent' : 'failed';
                        $errorMsg = $response->successful() ? null : $response->body();
                    } else {
                        $status = 'failed';
                        $errorMsg = 'WhatsApp Gateway API URL or API Key is not configured in settings.';
                    }
                }

                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'whatsapp',
                    'event_key' => $eventKey,
                    'recipient' => $cleanPhone,
                    'subject' => 'WhatsApp Notification',
                    'body' => $waMessage,
                    'status' => $status,
                    'error_message' => $errorMsg,
                    'sent_at' => Carbon::now(),
                ]);

                $results['whatsapp'] = $status;
            } catch (\Exception $e) {
                Log::error("WhatsApp dispatch error: " . $e->getMessage());
                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'whatsapp',
                    'event_key' => $eventKey,
                    'recipient' => $cleanPhone,
                    'subject' => 'WhatsApp Notification',
                    'body' => $waMessage,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'sent_at' => Carbon::now(),
                ]);
                $results['whatsapp'] = 'failed';
            }
        }

        // 3. SMS Notification
        if ($recipientPhone && ($trigger ? $trigger->sms_enabled : false) && ($setting ? $setting->sms_enabled : false) && $template->sms_body) {
            $smsMessage = self::compile($template->sms_body, $variables);
            $cleanPhone = preg_replace('/[^0-9]/', '', $recipientPhone);
            if (strlen($cleanPhone) === 10) {
                $cleanPhone = '91' . $cleanPhone;
            }

            try {
                $apiUrl = $setting->sms_api_url;
                $apiKey = $setting->sms_api_key;
                $sender = $setting->sms_sender_name;

                if ($apiUrl && $apiKey) {
                    $response = Http::timeout(10)->get($apiUrl, [
                        'message' => $smsMessage,
                        'sendername' => $sender,
                        'smstype' => 'TRANS',
                        'numbers' => $cleanPhone,
                        'apikey' => $apiKey,
                    ]);

                    $status = $response->successful() ? 'sent' : 'failed';
                    $errorMsg = $response->successful() ? null : $response->body();
                } else {
                    $status = 'failed';
                    $errorMsg = 'SMS Gateway API URL or API Key is not configured in settings.';
                }
                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'sms',
                    'event_key' => $eventKey,
                    'recipient' => $cleanPhone,
                    'subject' => 'SMS Notification',
                    'body' => $smsMessage,
                    'status' => $status,
                    'error_message' => $response->successful() ? null : $response->body(),
                    'sent_at' => Carbon::now(),
                ]);

                $results['sms'] = $status;
            } catch (\Exception $e) {
                Log::error("SMS dispatch error: " . $e->getMessage());
                NotificationLog::create([
                    'user_id' => $user->id,
                    'channel' => 'sms',
                    'event_key' => $eventKey,
                    'recipient' => $cleanPhone,
                    'subject' => 'SMS Notification',
                    'body' => $smsMessage,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'sent_at' => Carbon::now(),
                ]);
                $results['sms'] = 'failed';
            }
        }

        // 4. FCM Web/Mobile Push Notification
        if (($setting ? $setting->fcm_enabled : true)) {
            try {
                $fcmTokens = \App\Models\UserFcmToken::where('user_id', $user->id)->pluck('fcm_token')->toArray();
                if (!empty($fcmTokens)) {
                    $pushTitle = $template->name;
                    $pushBody = self::compile($template->whatsapp_body ?: $template->email_subject ?: 'Alert', $variables);

                    $sent = (new PushNotificationService())->send($fcmTokens, $pushTitle, $pushBody, array_merge($variables, ['event_key' => $eventKey, 'action_url' => $actionUrl]));
                    $results['fcm'] = $sent ? 'sent' : 'failed';

                    NotificationLog::create([
                        'user_id' => $user->id,
                        'channel' => 'fcm',
                        'event_key' => $eventKey,
                        'recipient' => count($fcmTokens) . ' device(s)',
                        'subject' => $pushTitle,
                        'body' => $pushBody,
                        'status' => $sent ? 'sent' : 'failed',
                        'sent_at' => Carbon::now(),
                    ]);
                }
            } catch (\Exception $fe) {
                Log::error("FCM dispatch error: " . $fe->getMessage());
            }
        }

        return $results;
    }
}
