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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gst_number')->nullable()->after('phone');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('hsn_code')->nullable()->after('sku');
            $table->decimal('price', 10, 2)->default(0.00)->after('hsn_code');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('supplier_id');
            $table->date('purchase_date')->nullable()->after('invoice_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['invoice_no', 'purchase_date']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'price']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('gst_number');
        });
    }
};
