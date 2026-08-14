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
        // 1. Notification Sender Settings (per store user)
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('whatsapp_enabled')->default(true);
            $table->string('whatsapp_api_url')->nullable();
            $table->string('whatsapp_api_key')->nullable();
            $table->string('whatsapp_app_name')->nullable();

            $table->boolean('sms_enabled')->default(false);
            $table->string('sms_api_url')->nullable();
            $table->string('sms_api_key')->nullable();
            $table->string('sms_sender_name')->nullable();

            $table->boolean('email_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamps();
        });

        // 2. Notification Templates (per store user)
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('key')->index(); // aging_30, aging_60, aging_90, sale_created, purchase_created, customer_payment, supplier_payment, low_stock_alert
            $table->string('name');
            $table->string('email_subject')->nullable();
            $table->text('email_body')->nullable();
            $table->text('whatsapp_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Notification Triggers Matrix (per store user)
        Schema::create('notification_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('event_key')->index();
            $table->string('event_name');
            $table->string('frequency')->default('instant'); // instant, daily, weekly, twice_a_week, three_times_a_week, once_a_month, twice_a_month, disabled
            $table->boolean('whatsapp_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->timestamps();
        });

        // 4. In-App User Notifications (for header bell)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type')->default('info'); // sale, purchase, payment, stock, aging, info
            $table->string('title');
            $table->text('message');
            $table->string('action_url')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // 5. Notification Queue & Logs Monitor
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('channel'); // whatsapp, sms, email, in_app
            $table->string('event_key')->nullable();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('status')->default('sent'); // pending, sent, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_triggers');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_settings');
    }
};
