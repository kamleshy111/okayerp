<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->string('whatsapp_provider')->default('whatsapp_web')->after('whatsapp_enabled');
            $table->string('meta_whatsapp_phone_number_id')->nullable()->after('whatsapp_app_name');
            $table->text('meta_whatsapp_access_token')->nullable()->after('meta_whatsapp_phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_provider', 'meta_whatsapp_phone_number_id', 'meta_whatsapp_access_token']);
        });
    }
};
