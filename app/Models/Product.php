<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;
use App\Models\Category;

class Product extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'name', 'sku', 'hsn_code', 'price', 'category_id', 'unit_type', 'stock_quantity', 'description', 'image', 'type'];

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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
