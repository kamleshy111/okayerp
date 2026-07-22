<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimateItem extends Model
{
    protected $fillable = [
        'estimate_id',
        'product_id',
        'quantity',
        'unit_type',
        'price',
        'base_price',
        'sgst',
        'cgst',
        'width',
        'height',
        'alternate_quantity',
        'alternate_unit_type',
        'description'
    ];

    public function estimate()
    {
        return $this->belongsTo(Estimate::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
