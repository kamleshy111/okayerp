<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class GstRate extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'rate',
        'cgst',
        'sgst',
        'igst',
        'is_active'
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'cgst' => 'decimal:2',
        'sgst' => 'decimal:2',
        'igst' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
