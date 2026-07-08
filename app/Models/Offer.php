<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'user_id', 'title', 'target_amount', 'start_date', 'end_date', 'reward_description'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
