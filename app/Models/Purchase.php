<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['supplier_id', 'transport_amount', 'gst_amount', 'accepted', 'total_amount', 'grand_total',
                            'paid', 'payment_method', 'payment_status'];


    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

}

