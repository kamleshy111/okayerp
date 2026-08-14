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
        // 1. User FCM Tokens table
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('fcm_token');
            $table->string('device_type')->default('web');
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        // 2. Add FCM Push settings columns to notification_settings
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('fcm_enabled')->default(true)->after('in_app_enabled');
            $table->text('firebase_credentials_json')->nullable()->after('fcm_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['fcm_enabled', 'firebase_credentials_json']);
        });

        Schema::dropIfExists('user_fcm_tokens');
    }
};
