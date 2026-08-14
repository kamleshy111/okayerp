<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'whatsapp_enabled',
        'whatsapp_provider',
        'whatsapp_api_url',
        'whatsapp_api_key',
        'whatsapp_app_name',
        'meta_whatsapp_phone_number_id',
        'meta_whatsapp_access_token',
        'sms_enabled',
        'sms_api_url',
        'sms_api_key',
        'sms_sender_name',
        'email_enabled',
        'in_app_enabled',
        'fcm_enabled',
        'firebase_credentials_json',
        'allow_sale_delete',
        'allow_purchase_delete',
    ];

    protected $casts = [
        'whatsapp_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'fcm_enabled' => 'boolean',
        'allow_sale_delete' => 'boolean',
        'allow_purchase_delete' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
