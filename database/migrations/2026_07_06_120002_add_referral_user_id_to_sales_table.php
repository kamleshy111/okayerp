<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('referral_user_id')->nullable()->after('customer_id');
            $table->foreign('referral_user_id')->references('id')->on('referral_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['referral_user_id']);
            $table->dropColumn('referral_user_id');
        });
    }
};
