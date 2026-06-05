<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePayment extends Model
{
    protected $fillable = [
        'supplier_id', 'amount', 'payment_date', 'payment_method', 'note'
    ];
}
