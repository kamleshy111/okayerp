<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class SaleReturn extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'sale_id',
        'return_no',
        'return_date',
        'refund_amount',
        'gst_refund_amount',
        'refund_method',
        'reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
