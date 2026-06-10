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
        Schema::create('estimates', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('customer_id');
            $table->string('estimate_no');
            $table->date('estimate_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->string('status')->default('Draft'); // Draft, Sent, Accepted, Invoiced, Declined, Expired
            $table->boolean('accepted')->default(1); // 1 = GST, 0 = Non-GST
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estimates');
    }
};
