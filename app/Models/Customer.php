<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['user_id', 'name', 'email', 'phone', 'gst_number', 'pan_number', 'cin_number', 'address', 'city', 'district', 'state', 'country', 'pin_code', 'status'];

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
}
