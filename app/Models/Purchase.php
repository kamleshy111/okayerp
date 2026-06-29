<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Purchase extends Model
{
    use Auditable;

    protected $fillable = ['supplier_id', 'invoice_no', 'purchase_date', 'transport_amount', 'gst_amount', 'accepted', 'total_amount', 'grand_total',
                            'paid', 'payment_method', 'payment_status', 'received_date', 'delivery_mode', 'delivery_person_name', 'delivery_person_phone', 'vehicle_type', 'vehicle_number'];


    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }
}

