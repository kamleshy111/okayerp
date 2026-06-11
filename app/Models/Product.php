<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class Product extends Model
{
    use Auditable;

    protected $fillable = [ 'user_id', 'name', 'sku', 'hsn_code', 'price', 'category_id', 'unit_type', 'cgst', 'sgst', 'stock_quantity', 'description'];

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
