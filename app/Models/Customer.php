<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'phone', 'gst_number', 'pan_number', 'cin_number', 'address', 'city', 'district', 'state', 'country', 'pin_code', 'status', 'last_whatsapp_sent_at', 'last_whatsapp_bucket', 'last_sms_sent_at', 'last_sms_bucket'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    public function customerProducts()
    {
        return $this->hasMany(CustomerProduct::class);
    }

    public function assignedProducts()
    {
        return $this->belongsToMany(Product::class, 'customer_products')
            ->withPivot('sale_price')
            ->withTimestamps();
    }
}
