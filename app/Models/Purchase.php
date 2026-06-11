<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Purchase extends Model
{
    use Auditable;

    protected $fillable = ['supplier_id', 'invoice_no', 'purchase_date', 'transport_amount', 'gst_amount', 'accepted', 'total_amount', 'grand_total',
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

