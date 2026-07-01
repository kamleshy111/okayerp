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
        Schema::table('sale_returns', function (Blueprint $table) {
            // SQLite does not support changing columns easily, so only change if not SQLite
            if (DB::getDriverName() !== 'sqlite') {
                $table->unsignedBigInteger('sale_id')->nullable()->change();
            }
            
            if (DB::getDriverName() === 'sqlite' || !Schema::hasColumn('sale_returns', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->constrained()->onDelete('cascade');
            }
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite' || !Schema::hasColumn('sale_return_items', 'sale_id')) {
                $table->foreignId('sale_id')->nullable()->constrained()->onDelete('cascade');
            }
            if (DB::getDriverName() === 'sqlite' || !Schema::hasColumn('sale_return_items', 'due_deduction')) {
                $table->decimal('due_deduction', 10, 2)->default(0.00);
            }
        });

        // Migrate existing data
        $returns = DB::table('sale_returns')->get();
        foreach ($returns as $return) {
            $sale = DB::table('sales')->where('id', $return->sale_id)->first();
            if ($sale) {
                DB::table('sale_returns')->where('id', $return->id)->update([
                    'customer_id' => $sale->customer_id
                ]);
                DB::table('sale_return_items')->where('sale_return_id', $return->id)->update([
                    'sale_id' => $sale->id
                ]);

                // Copy due_deduction to the first item for clean item-level due calculation
                $firstItem = DB::table('sale_return_items')->where('sale_return_id', $return->id)->first();
                if ($firstItem) {
                    DB::table('sale_return_items')->where('id', $firstItem->id)->update([
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
        Schema::table('sale_return_items', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite' || Schema::hasColumn('sale_return_items', 'due_deduction')) {
                $table->dropColumn('due_deduction');
            }
            if (DB::getDriverName() === 'sqlite' || Schema::hasColumn('sale_return_items', 'sale_id')) {
                $table->dropForeign(['sale_id']);
                $table->dropColumn('sale_id');
            }
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite' || Schema::hasColumn('sale_returns', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
            if (DB::getDriverName() !== 'sqlite') {
                $table->unsignedBigInteger('sale_id')->nullable(false)->change();
            }
        });
    }
};
