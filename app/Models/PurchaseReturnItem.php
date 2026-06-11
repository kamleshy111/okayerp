<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PurchaseReturnItem extends Model
{
    use Auditable;

    protected $fillable = [
        'purchase_return_id',
        'product_id',
        'quantity',
        'price',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
