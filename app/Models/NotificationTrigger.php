<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_key',
        'event_name',
        'frequency',
        'whatsapp_enabled',
        'sms_enabled',
        'email_enabled',
        'in_app_enabled',
    ];

    protected $casts = [
        'whatsapp_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
