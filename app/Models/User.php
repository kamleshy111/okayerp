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
        'city',
        'district',
        'state',
        'country',
        'pin_code',
        'pan_number',
        'cin_number',
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
