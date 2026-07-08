<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralUser extends Model
{
    protected $fillable = [
        'user_id', 'name', 'phone', 'email', 'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'referral_user_id');
    }

    public function referralSales()
    {
        return $this->hasMany(ReferralSale::class, 'referral_user_id');
    }
}
