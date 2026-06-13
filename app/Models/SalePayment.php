<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Auditable;

class SalePayment extends Model
{
    use Auditable;

    protected $fillable = [
        'customer_id', 'sale_id', 'amount', 'payment_date', 'payment_method', 'note', 'accepted'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
