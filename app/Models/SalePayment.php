<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    protected $fillable = [
        'customer_id', 'amount', 'payment_date', 'payment_method', 'note'
    ];
}
