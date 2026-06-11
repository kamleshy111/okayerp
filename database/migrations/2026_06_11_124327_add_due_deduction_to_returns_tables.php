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
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->decimal('due_deduction', 15, 2)->default(0.00)->after('gst_refund_amount');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->decimal('due_deduction', 15, 2)->default(0.00)->after('gst_refund_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table) {
            $table->dropColumn('due_deduction');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropColumn('due_deduction');
        });
    }
};
