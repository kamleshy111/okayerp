<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'phone' => $request->user()->phone,
                    'address' => $request->user()->address,
                    'city' => $request->user()->city,
                    'district' => $request->user()->district,
                    'state' => $request->user()->state,
                    'country' => $request->user()->country,
                    'pin_code' => $request->user()->pin_code,
                    'profile_photo' => $request->user()->profile_photo,
                    'bank_name' => $request->user()->bank_name,
                    'account_number' => $request->user()->account_number,
                    'ifsc_code' => $request->user()->ifsc_code,
                    'branch_name' => $request->user()->branch_name,
                    'gstin' => $request->user()->gstin,
                    'invoice_title_without_gst' => $request->user()->invoice_title_without_gst,
                    'invoice_title_with_gst' => $request->user()->invoice_title_with_gst,
                    'invoice_print_size' => $request->user()->invoice_print_size,
                    'hide_bank_details' => $request->user()->hide_bank_details,
                    'role' => $request->user()->role,
                    'roles' => $request->user()->roles->pluck('name'),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    
                    // Automatic Reminders Settings
                    'auto_whatsapp_reminders_enabled' => $request->user()->auto_whatsapp_reminders_enabled,
                    'auto_whatsapp_30_frequency' => $request->user()->auto_whatsapp_30_frequency,
                    'auto_whatsapp_60_frequency' => $request->user()->auto_whatsapp_60_frequency,
                    'auto_whatsapp_90_frequency' => $request->user()->auto_whatsapp_90_frequency,
                    'whatsapp_api_url' => $request->user()->whatsapp_api_url,
                    'whatsapp_api_key' => $request->user()->whatsapp_api_key,
                    'whatsapp_app_name' => $request->user()->whatsapp_app_name,
                    'whatsapp_message_template' => $request->user()->whatsapp_message_template,
                    
                    'auto_sms_reminders_enabled' => $request->user()->auto_sms_reminders_enabled,
                    'auto_sms_30_frequency' => $request->user()->auto_sms_30_frequency,
                    'auto_sms_60_frequency' => $request->user()->auto_sms_60_frequency,
                    'auto_sms_90_frequency' => $request->user()->auto_sms_90_frequency,
                    'sms_api_url' => $request->user()->sms_api_url,
                    'sms_api_key' => $request->user()->sms_api_key,
                    'sms_sender_name' => $request->user()->sms_sender_name,
                    'sms_message_template' => $request->user()->sms_message_template,
                ] : null,
            ],
            'session' => [
                'orig_user' => session('orig_user'),
            ],
            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
            'gst_states' => collect(config('gst_states'))->map(fn($name, $code) => [
                'code' => $code,
                'name' => $name,
                'display' => $name
            ])->values()->all(),
            'state_cities' => config('state_cities'),
            'countries' => config('countries'),
        ];
    }
}
