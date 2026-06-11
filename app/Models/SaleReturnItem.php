<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class SaleReturnItem extends Model
{
    use Auditable;

    protected $fillable = [
        'sale_return_id',
        'product_id',
        'quantity',
        'price',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
