<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Income extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'income_category_id', 'received_from', 'amount', 'date', 'description', 'reference_no'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
    }
}
