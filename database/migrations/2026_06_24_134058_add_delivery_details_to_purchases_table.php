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
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('received_date')->nullable();
            $table->string('delivery_mode')->nullable(); // 'By Hand', 'Vehicle'
            $table->string('delivery_person_name')->nullable();
            $table->string('delivery_person_phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn([
                'received_date',
                'delivery_mode',
                'delivery_person_name',
                'delivery_person_phone',
                'vehicle_type',
                'vehicle_number'
            ]);
        });
    }
};
