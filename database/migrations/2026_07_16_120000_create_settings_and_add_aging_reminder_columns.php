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
        // 1. Add store-level separate reminder settings and API configs to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('auto_whatsapp_reminders_enabled')->default(false)->after('last_closed_date');
            $table->string('auto_whatsapp_30_frequency')->default('weekly')->after('auto_whatsapp_reminders_enabled');
            $table->string('auto_whatsapp_60_frequency')->default('twice_a_week')->after('auto_whatsapp_30_frequency');
            $table->string('auto_whatsapp_90_frequency')->default('three_times_a_week')->after('auto_whatsapp_60_frequency');

            // WhatsApp API Settings
            $table->string('whatsapp_api_url')->nullable()->after('auto_whatsapp_90_frequency');
            $table->string('whatsapp_api_key')->nullable()->after('whatsapp_api_url');
            $table->string('whatsapp_app_name')->nullable()->after('whatsapp_api_key');
            $table->text('whatsapp_message_template')->nullable()->after('whatsapp_app_name');

            $table->boolean('auto_sms_reminders_enabled')->default(false)->after('whatsapp_message_template');
            $table->string('auto_sms_30_frequency')->default('weekly')->after('auto_sms_reminders_enabled');
            $table->string('auto_sms_60_frequency')->default('twice_a_week')->after('auto_sms_30_frequency');
            $table->string('auto_sms_90_frequency')->default('three_times_a_week')->after('auto_sms_60_frequency');

            // SMS API Settings
            $table->string('sms_api_url')->nullable()->after('auto_sms_90_frequency');
            $table->string('sms_api_key')->nullable()->after('sms_api_url');
            $table->string('sms_sender_name')->nullable()->after('sms_api_key');
            $table->text('sms_message_template')->nullable()->after('sms_sender_name');
        });

        // 2. Add separate tracking to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('last_whatsapp_sent_at')->nullable()->after('status');
            $table->string('last_whatsapp_bucket')->nullable()->after('last_whatsapp_sent_at');
            $table->timestamp('last_sms_sent_at')->nullable()->after('last_whatsapp_bucket');
            $table->string('last_sms_bucket')->nullable()->after('last_sms_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'last_whatsapp_sent_at',
                'last_whatsapp_bucket',
                'last_sms_sent_at',
                'last_sms_bucket'
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'auto_whatsapp_reminders_enabled',
                'auto_whatsapp_30_frequency',
                'auto_whatsapp_60_frequency',
                'auto_whatsapp_90_frequency',
                'whatsapp_api_url',
                'whatsapp_api_key',
                'whatsapp_app_name',
                'whatsapp_message_template',
                'auto_sms_reminders_enabled',
                'auto_sms_30_frequency',
                'auto_sms_60_frequency',
                'auto_sms_90_frequency',
                'sms_api_url',
                'sms_api_key',
                'sms_sender_name',
                'sms_message_template'
            ]);
        });
    }
};
