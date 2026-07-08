<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSale extends Model
{
    protected $fillable = [
        'sale_id', 'referral_user_id', 'sale_amount',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function referralUser()
    {
        return $this->belongsTo(ReferralUser::class);
    }
}
