<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('referral_user_id');
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('referral_user_id')->references('id')->on('referral_users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_sales');
    }
};
