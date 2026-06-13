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
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id')->nullable()->after('supplier_id');
        });

        // Create PurchasePayment records for existing purchases with a paid amount
        $purchases = \Illuminate\Support\Facades\DB::table('purchases')->where('paid', '>', 0)->get();
        foreach ($purchases as $purchase) {
            \Illuminate\Support\Facades\DB::table('purchase_payments')->insert([
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'amount' => $purchase->paid,
                'payment_date' => $purchase->purchase_date ? date('Y-m-d', strtotime($purchase->purchase_date)) : date('Y-m-d'),
                'payment_method' => $purchase->payment_method ?: 'Cash',
                'note' => "Payment for Purchase Invoice #{$purchase->id}",
                'accepted' => $purchase->accepted,
                'created_at' => $purchase->created_at ?: now(),
                'updated_at' => $purchase->updated_at ?: now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->dropColumn('purchase_id');
        });
    }
};
