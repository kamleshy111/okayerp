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
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_id')->nullable()->after('customer_id');
        });

        // Create SalePayment records for existing sales with a paid amount
        $sales = \Illuminate\Support\Facades\DB::table('sales')->where('paid', '>', 0)->get();
        foreach ($sales as $sale) {
            \Illuminate\Support\Facades\DB::table('sale_payments')->insert([
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'amount' => $sale->paid,
                'payment_date' => $sale->created_at ? date('Y-m-d', strtotime($sale->created_at)) : date('Y-m-d'),
                'payment_method' => $sale->payment_method ?: 'Cash',
                'note' => "Payment for Sale Invoice #{$sale->id}",
                'accepted' => $sale->accepted,
                'created_at' => $sale->created_at ?: now(),
                'updated_at' => $sale->updated_at ?: now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropColumn('sale_id');
        });
    }
};
