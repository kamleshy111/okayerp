<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Sale extends Model
{
    use Auditable;

    protected $fillable = [
        'customer_id', 'estimate_id', 'gst_amount', 'discount', 'total_amount', 'grand_total', 'accepted', 'paid', 'payment_method', 'payment_status'
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
}
