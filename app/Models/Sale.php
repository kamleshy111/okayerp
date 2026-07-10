<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Sale extends Model
{
    use Auditable;

    protected $fillable = [
        'customer_id', 'estimate_id', 'referral_user_id', 'sale_date', 'gst_amount', 'discount', 'total_amount', 'grand_total', 'accepted', 'paid', 'payment_method', 'payment_status', 'currency', 'exchange_rate'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function saleReturns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function saleReturnItems()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function referralUser()
    {
        return $this->belongsTo(ReferralUser::class);
    }

    public function referralSale()
    {
        return $this->hasOne(ReferralSale::class);
    }
}
