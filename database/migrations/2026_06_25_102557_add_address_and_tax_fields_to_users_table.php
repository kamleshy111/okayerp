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
            $table->string('city')->nullable()->after('address');
            $table->string('district')->nullable()->after('city');
            $table->string('state')->nullable()->after('district');
            $table->string('country')->nullable()->after('state');
            $table->string('pin_code')->nullable()->after('country');
            $table->string('pan_number')->nullable()->after('pin_code');
            $table->string('cin_number')->nullable()->after('pan_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['city', 'district', 'state', 'country', 'pin_code', 'pan_number', 'cin_number']);
        });
    }
};
