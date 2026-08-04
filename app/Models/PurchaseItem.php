<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'quantity', 'price', 'base_price', 'sgst', 'cgst', 'unit_type', 'width', 'height', 'alternate_quantity', 'alternate_unit_type', 'description'];

    public function getUnitPriceAttribute()
    {
        return $this->attributes['price'] ?? $this->attributes['unit_price'] ?? 0;
    }

    public function getTaxRateAttribute()
    {
        $sgst = (float)($this->attributes['sgst'] ?? 0);
        $cgst = (float)($this->attributes['cgst'] ?? 0);
        return $sgst + $cgst;
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}
