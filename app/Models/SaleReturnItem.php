<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class SaleReturnItem extends Model
{
    use Auditable;

    protected $fillable = [
        'sale_return_id',
        'sale_id',
        'product_id',
        'quantity',
        'price',
        'due_deduction',
    ];

    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
