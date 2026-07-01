<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->unsignedBigInteger('purchase_id')->nullable()->change();
            }
            if (DB::getDriverName() === 'sqlite' || !Schema::hasColumn('purchase_returns', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('cascade');
            }
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite' || !Schema::hasColumn('purchase_return_items', 'purchase_id')) {
                $table->foreignId('purchase_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (DB::getDriverName() === 'sqlite' || !Schema::hasColumn('purchase_return_items', 'due_deduction')) {
                $table->decimal('due_deduction', 10, 2)->default(0.00);
            }
        });

        // Migrate existing data
        $returns = DB::table('purchase_returns')->get();
        foreach ($returns as $return) {
            $purchase = DB::table('purchases')->where('id', $return->purchase_id)->first();
            if ($purchase) {
                DB::table('purchase_returns')->where('id', $return->id)->update([
                    'supplier_id' => $purchase->supplier_id
                ]);
                DB::table('purchase_return_items')->where('purchase_return_id', $return->id)->update([
                    'purchase_id' => $purchase->id
                ]);

                // Copy due_deduction to the first item for clean item-level due calculation
                $firstItem = DB::table('purchase_return_items')->where('purchase_return_id', $return->id)->first();
                if ($firstItem) {
                    DB::table('purchase_return_items')->where('id', $firstItem->id)->update([
                        'due_deduction' => $return->due_deduction ?? 0.00
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite' || Schema::hasColumn('purchase_return_items', 'due_deduction')) {
                $table->dropColumn('due_deduction');
            }
            if (DB::getDriverName() === 'sqlite' || Schema::hasColumn('purchase_return_items', 'purchase_id')) {
                $table->dropForeign(['purchase_id']);
                $table->dropColumn('purchase_id');
            }
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite' || Schema::hasColumn('purchase_returns', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
            if (DB::getDriverName() !== 'sqlite') {
                $table->unsignedBigInteger('purchase_id')->nullable(false)->change();
            }
        });
    }
};
