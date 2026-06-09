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
            $table->string('ledger_pin')->nullable()->after('password');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_email_unique');
            $table->unique(['user_id', 'email']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');
            $table->unique(['user_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'sku']);
            $table->unique('sku');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'email']);
            $table->unique('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ledger_pin');
        });
    }
};
