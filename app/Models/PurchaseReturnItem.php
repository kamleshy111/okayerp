<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class PurchaseReturnItem extends Model
{
    use Auditable;

    protected $fillable = [
        'purchase_return_id',
        'purchase_id',
        'product_id',
        'quantity',
        'price',
        'due_deduction',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }
}
