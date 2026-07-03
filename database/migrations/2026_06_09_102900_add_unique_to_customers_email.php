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
        // 1. If 'ledger_pin' already exists in 'users' due to a half-failed run of 2026_06_09_103000, drop it
        // so that 2026_06_09_103000 can add it cleanly.
        if (Schema::hasColumn('users', 'ledger_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('ledger_pin');
            });
        }

        // 2. Add 'customers_email_unique' so that 2026_06_09_103000 can drop it cleanly.
        if (Schema::hasTable('customers') && !Schema::hasIndex('customers', 'customers_email_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique('email', 'customers_email_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasIndex('customers', 'customers_email_unique')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropUnique('customers_email_unique');
            });
        }
    }
};
