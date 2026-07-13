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
        Schema::table('users', function (Blueprint $table) {
            $table->string('invoice_title_without_gst')->nullable()->after('gstin');
            $table->string('invoice_title_with_gst')->nullable()->after('invoice_title_without_gst');
            $table->boolean('hide_bank_details')->default(false)->after('invoice_title_with_gst');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invoice_title_without_gst', 'invoice_title_with_gst', 'hide_bank_details']);
        });
    }
};
