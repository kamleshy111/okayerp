<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if (empty($data['ledger_pin'])) {
            unset($data['ledger_pin']);
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        // Handle profile photo
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo && Storage::disk('public')->exists($user->profile_photo)) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Save new photo
            $file = $request->file('profile_photo');
            $filename = time() . '.' . $file->getClientOriginalExtension();

            $path = $file->storeAs('uploads/image', $filename, 'public');
            $user->profile_photo = $path;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Update the user's automatic reminders settings.
     */
    public function updateReminders(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'auto_whatsapp_reminders_enabled' => ['nullable', 'boolean'],
            'auto_whatsapp_30_frequency' => ['nullable', 'string', 'in:disabled,weekly,twice_a_week,three_times_a_week,daily,once_a_month,twice_a_month'],
            'auto_whatsapp_60_frequency' => ['nullable', 'string', 'in:disabled,weekly,twice_a_week,three_times_a_week,daily,once_a_month,twice_a_month'],
            'auto_whatsapp_90_frequency' => ['nullable', 'string', 'in:disabled,weekly,twice_a_week,three_times_a_week,daily,once_a_month,twice_a_month'],
            'whatsapp_api_url' => ['nullable', 'string', 'max:255'],
            'whatsapp_api_key' => ['nullable', 'string', 'max:255'],
            'whatsapp_app_name' => ['nullable', 'string', 'max:255'],
            'whatsapp_message_template' => ['nullable', 'string'],

            'auto_sms_reminders_enabled' => ['nullable', 'boolean'],
            'auto_sms_30_frequency' => ['nullable', 'string', 'in:disabled,weekly,twice_a_week,three_times_a_week,daily,once_a_month,twice_a_month'],
            'auto_sms_60_frequency' => ['nullable', 'string', 'in:disabled,weekly,twice_a_week,three_times_a_week,daily,once_a_month,twice_a_month'],
            'auto_sms_90_frequency' => ['nullable', 'string', 'in:disabled,weekly,twice_a_week,three_times_a_week,daily,once_a_month,twice_a_month'],
            'sms_api_url' => ['nullable', 'string', 'max:255'],
            'sms_api_key' => ['nullable', 'string', 'max:255'],
            'sms_sender_name' => ['nullable', 'string', 'max:255'],
            'sms_message_template' => ['nullable', 'string'],
        ]);

        $data['auto_whatsapp_reminders_enabled'] = isset($data['auto_whatsapp_reminders_enabled']) ? (bool)$data['auto_whatsapp_reminders_enabled'] : false;
        $data['auto_sms_reminders_enabled'] = isset($data['auto_sms_reminders_enabled']) ? (bool)$data['auto_sms_reminders_enabled'] : false;

        $user->update($data);

        return Redirect::route('profile.edit')->with('status', 'reminders-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
