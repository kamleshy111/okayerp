<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class PurchasePayment extends Model
{
    use Auditable;

    protected $fillable = [
        'supplier_id', 'amount', 'payment_date', 'payment_method', 'note', 'accepted'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
