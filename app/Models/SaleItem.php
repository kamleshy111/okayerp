<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'quantity', 'price', 'base_price', 'sgst', 'cgst', 'unit_type', 'width', 'height', 'alternate_quantity', 'alternate_unit_type', 'description'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
