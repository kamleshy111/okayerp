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
            if (!Schema::hasColumn('customers', 'pan_number')) {
                $table->string('pan_number')->nullable()->after('gst_number');
            }
            if (!Schema::hasColumn('customers', 'cin_number')) {
                $table->string('cin_number')->nullable()->after('pan_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['pan_number', 'cin_number']);
        });
    }
};
