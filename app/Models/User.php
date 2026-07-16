<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'phone',
        'address',
        'profile_photo',
        'email',
        'password',
        'ledger_pin',
        'bank_name',
        'account_number',
        'ifsc_code',
        'branch_name',
        'gstin',
        'invoice_title_without_gst',
        'invoice_title_with_gst',
        'invoice_print_size',
        'hide_bank_details',
        'city',
        'district',
        'state',
        'country',
        'pin_code',
        'pan_number',
        'cin_number',
        'last_closed_date',
        'auto_whatsapp_reminders_enabled',
        'auto_whatsapp_30_frequency',
        'auto_whatsapp_60_frequency',
        'auto_whatsapp_90_frequency',
        'whatsapp_api_url',
        'whatsapp_api_key',
        'whatsapp_app_name',
        'whatsapp_message_template',
        'auto_sms_reminders_enabled',
        'auto_sms_30_frequency',
        'auto_sms_60_frequency',
        'auto_sms_90_frequency',
        'sms_api_url',
        'sms_api_key',
        'sms_sender_name',
        'sms_message_template',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ledger_pin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ledger_pin' => 'hashed',
            'hide_bank_details' => 'boolean',
            'auto_whatsapp_reminders_enabled' => 'boolean',
            'auto_sms_reminders_enabled' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::created(function ($user) {
            if ($user->role) {
                $user->assignRole($user->role);
                
                if ($user->role === 'store') {
                    $permissions = \Spatie\Permission\Models\Permission::pluck('name')->toArray();
                    $user->givePermissionTo($permissions);
                }
            }
        });

        static::updated(function ($user) {
            if ($user->isDirty('role') && $user->role) {
                $user->syncRoles([$user->role]);
                
                if ($user->role === 'store') {
                    $permissions = \Spatie\Permission\Models\Permission::pluck('name')->toArray();
                    $user->givePermissionTo($permissions);
                }
            }
        });
    }
}
