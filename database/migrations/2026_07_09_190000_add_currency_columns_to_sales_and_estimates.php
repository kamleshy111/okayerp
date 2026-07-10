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
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (!Schema::hasColumn('sales', 'currency')) {
                    $table->string('currency', 10)->nullable()->after('accepted');
                }
                if (!Schema::hasColumn('sales', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 10, 4)->nullable()->after('currency');
                }
            });
        }

        if (Schema::hasTable('estimates')) {
            Schema::table('estimates', function (Blueprint $table) {
                if (!Schema::hasColumn('estimates', 'currency')) {
                    $table->string('currency', 10)->nullable()->after('notes');
                }
                if (!Schema::hasColumn('estimates', 'exchange_rate')) {
                    $table->decimal('exchange_rate', 10, 4)->nullable()->after('currency');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn(['currency', 'exchange_rate']);
            });
        }

        if (Schema::hasTable('estimates')) {
            Schema::table('estimates', function (Blueprint $table) {
                $table->dropColumn(['currency', 'exchange_rate']);
            });
        }
    }
};
