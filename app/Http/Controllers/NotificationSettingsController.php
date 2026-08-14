<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\NotificationTemplate;
use App\Models\NotificationTrigger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class NotificationSettingsController extends Controller
{
    /**
     * Page 1: Sender Info & Gateway Settings (WhatsApp/SMS/Email/In-App)
     */
    public function senderInfo()
    {
        $user = Auth::user();
        NotificationService::ensureDefaults($user);
        $setting = NotificationSetting::where('user_id', $user->id)->first();

        return Inertia::render('NotificationSettings/SenderInfo', [
            'setting' => $setting,
        ]);
    }

    /**
     * Update Sender Info Settings.
     */
    public function updateSenderInfo(Request $request)
    {
        $user = Auth::user();
        $setting = NotificationSetting::where('user_id', $user->id)->first();

        $validated = $request->validate([
            'whatsapp_enabled' => 'boolean',
            'whatsapp_provider' => 'nullable|string', // whatsapp_web, gateway_api, meta_cloud
            'whatsapp_api_url' => 'nullable|string',
            'whatsapp_api_key' => 'nullable|string',
            'whatsapp_app_name' => 'nullable|string',
            'meta_whatsapp_phone_number_id' => 'nullable|string',
            'meta_whatsapp_access_token' => 'nullable|string',
            'sms_enabled' => 'boolean',
            'sms_api_url' => 'nullable|string',
            'sms_api_key' => 'nullable|string',
            'sms_sender_name' => 'nullable|string',
            'email_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
            'fcm_enabled' => 'boolean',
            'allow_sale_delete' => 'boolean',
            'allow_purchase_delete' => 'boolean',
            'firebase_project_id' => 'nullable|string',
            'firebase_credentials_json' => 'nullable|string',
        ]);

        if ($setting) {
            $setting->update($validated);
        }

        // Keep legacy User table columns in sync so legacy functions work seamlessly!
        $user->update([
            'auto_whatsapp_reminders_enabled' => $validated['whatsapp_enabled'] ?? false,
            'whatsapp_api_url' => $validated['whatsapp_api_url'] ?? null,
            'whatsapp_api_key' => $validated['whatsapp_api_key'] ?? null,
            'whatsapp_app_name' => $validated['whatsapp_app_name'] ?? null,
            'auto_sms_reminders_enabled' => $validated['sms_enabled'] ?? false,
            'sms_api_url' => $validated['sms_api_url'] ?? null,
            'sms_api_key' => $validated['sms_api_key'] ?? null,
            'sms_sender_name' => $validated['sms_sender_name'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Notification Sender & Gateway settings updated successfully.');
    }

    /**
     * Fetch Live WhatsApp Web QR Code for Web UI Scanner
     */
    public function getWhatsAppQrCode(Request $request)
    {
        $user = Auth::user();
        $setting = NotificationSetting::where('user_id', $user->id)->first();
        $storeCode = 'store_' . $user->id;

        // Always use WHATSAPP_NODE_URL from env / config for Web WhatsApp QR scanner
        $baseUrl = config('services.whatsapp.node_url', 'http://localhost:3000/send-message');
        
        // Convert /send-message or /send to /qr-code
        if (str_contains($baseUrl, '/send')) {
            $nodeUrl = preg_replace('/\/send(-message)?$/', '/qr-code', $baseUrl);
        } elseif (!str_contains($baseUrl, '/qr-code') && !str_contains($baseUrl, '/session-status')) {
            $nodeUrl = rtrim($baseUrl, '/') . '/qr-code';
        } else {
            $nodeUrl = $baseUrl;
        }

        $secretToken = config('services.whatsapp.node_secret_token', 'master_secret_token_2026');

        // Resolve host IP for Docker/DDEV environments ONLY if referencing localhost/127.0.0.1
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

        try {
            $response = Http::timeout(8)->withHeaders([
                'X-KR-Timestamp' => $timestamp,
                'X-KR-Signature' => $signature,
                'Authorization'  => 'Bearer ' . $secretToken,
            ])->get($nodeUrl, [
                'school_code' => $storeCode,
                'store_code'  => $storeCode,
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'is_connected' => false,
                'message' => 'Gateway Response Error: HTTP ' . $response->status() . ' - ' . ($response->json('error') ?? $response->body()),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'is_connected' => false,
                'message' => 'WhatsApp Gateway Connection Error (' . $nodeUrl . '): ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Test WhatsApp Message Sending
     */
    public function testWhatsApp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $user = Auth::user();
        $result = (new NotificationService())->dispatchInline(
            $user,
            'sale_created',
            [
                'customer_name' => 'Test User',
                'invoice_no' => 'TEST-001',
                'amount' => '100.00',
                'date' => date('Y-m-d'),
                'pdf_url' => '#',
            ],
            $request->phone,
            null,
            null
        );

        return response()->json([
            'success' => isset($result['whatsapp']) && $result['whatsapp'] === 'sent',
            'results' => $result,
            'message' => (isset($result['whatsapp']) && $result['whatsapp'] === 'sent') ? 'Test WhatsApp message dispatched successfully!' : 'Failed to send WhatsApp message. Please check Gateway status.',
        ]);
    }

    /**
     * Page 2: Message Templates Management
     */
    public function templates()
    {
        $user = Auth::user();
        NotificationService::ensureDefaults($user);
        $templates = NotificationTemplate::where('user_id', $user->id)->orderBy('id', 'asc')->get();

        return Inertia::render('NotificationSettings/Templates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Update Message Templates.
     */
    public function updateTemplate(Request $request, $id)
    {
        $user = Auth::user();
        $template = NotificationTemplate::where('user_id', $user->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'email_subject' => 'nullable|string',
            'email_body' => 'nullable|string',
            'whatsapp_body' => 'nullable|string',
            'sms_body' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $template->update($validated);

        return redirect()->back()->with('success', "Template '{$template->name}' updated successfully.");
    }

    /**
     * Page 3: Notification Triggers Matrix & Frequencies
     */
    public function matrix()
    {
        $user = Auth::user();
        NotificationService::ensureDefaults($user);
        $triggers = NotificationTrigger::where('user_id', $user->id)->orderBy('id', 'asc')->get();

        return Inertia::render('NotificationSettings/Matrix', [
            'triggers' => $triggers,
        ]);
    }

    /**
     * Update Notification Trigger Matrix.
     */
    public function updateMatrix(Request $request)
    {
        $user = Auth::user();
        $triggersData = $request->input('triggers', []);

        foreach ($triggersData as $trig) {
            if (isset($trig['id'])) {
                $trigger = NotificationTrigger::where('user_id', $user->id)->where('id', $trig['id'])->first();
                if ($trigger) {
                    $trigger->update([
                        'frequency' => $trig['frequency'] ?? 'instant',
                        'whatsapp_enabled' => (bool)($trig['whatsapp_enabled'] ?? false),
                        'sms_enabled' => (bool)($trig['sms_enabled'] ?? false),
                        'email_enabled' => (bool)($trig['email_enabled'] ?? false),
                        'in_app_enabled' => (bool)($trig['in_app_enabled'] ?? false),
                    ]);

                    // Sync legacy user frequency columns if aging
                    if ($trigger->event_key === 'aging_30') {
                        $user->update(['auto_whatsapp_30_frequency' => $trig['frequency']]);
                    } elseif ($trigger->event_key === 'aging_60') {
                        $user->update(['auto_whatsapp_60_frequency' => $trig['frequency']]);
                    } elseif ($trigger->event_key === 'aging_90') {
                        $user->update(['auto_whatsapp_90_frequency' => $trig['frequency']]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Notification Triggers Matrix updated successfully.');
    }

    /**
     * Page 4: Notification Logs & Queue Monitor
     */
    public function logs(Request $request)
    {
        $user = Auth::user();
        NotificationService::ensureDefaults($user);

        $query = NotificationLog::where('user_id', $user->id)->orderBy('created_at', 'desc');

        if ($request->has('channel') && $request->channel !== 'all') {
            $query->where('channel', $request->channel);
        }
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $logs = $query->paginate(20)->withQueryString();

        return Inertia::render('NotificationSettings/Logs', [
            'logs' => $logs,
            'filters' => [
                'channel' => $request->channel ?? 'all',
                'status' => $request->status ?? 'all',
            ],
        ]);
    }

    /**
     * Clear notification logs.
     */
    public function clearLogs()
    {
        $user = Auth::user();
        NotificationLog::where('user_id', $user->id)->delete();

        return redirect()->back()->with('success', 'Notification logs cleared.');
    }
}
