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
                    'role' => $request->user()->role,
                    'roles' => $request->user()->roles->pluck('name'),
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
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
                'display' => "$name ($code)"
            ])->values()->all(),
        ];
    }
}
