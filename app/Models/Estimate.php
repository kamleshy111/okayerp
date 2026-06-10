<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estimate extends Model
{
    protected $fillable = [
        'customer_id',
        'estimate_no',
        'estimate_date',
        'expiry_date',
        'gst_amount',
        'discount',
        'total_amount',
        'grand_total',
        'status',
        'accepted',
        'notes'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(EstimateItem::class);
    }
}
