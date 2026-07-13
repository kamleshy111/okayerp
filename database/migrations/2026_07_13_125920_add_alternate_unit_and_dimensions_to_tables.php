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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('width', 8, 2)->nullable()->after('unit_type');
            $table->decimal('height', 8, 2)->nullable()->after('width');
            $table->string('alternate_unit_type')->nullable()->after('height');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('width', 8, 2)->nullable()->after('unit_type');
            $table->decimal('height', 8, 2)->nullable()->after('width');
            $table->decimal('alternate_quantity', 10, 2)->nullable()->after('height');
            $table->string('alternate_unit_type')->nullable()->after('alternate_quantity');
        });

        Schema::table('estimate_items', function (Blueprint $table) {
            $table->decimal('width', 8, 2)->nullable()->after('unit_type');
            $table->decimal('height', 8, 2)->nullable()->after('width');
            $table->decimal('alternate_quantity', 10, 2)->nullable()->after('height');
            $table->string('alternate_unit_type')->nullable()->after('alternate_quantity');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('width', 8, 2)->nullable()->after('unit_type');
            $table->decimal('height', 8, 2)->nullable()->after('width');
            $table->decimal('alternate_quantity', 10, 2)->nullable()->after('height');
            $table->string('alternate_unit_type')->nullable()->after('alternate_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'alternate_quantity', 'alternate_unit_type']);
        });

        Schema::table('estimate_items', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'alternate_quantity', 'alternate_unit_type']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'alternate_quantity', 'alternate_unit_type']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'alternate_unit_type']);
        });
    }
};
