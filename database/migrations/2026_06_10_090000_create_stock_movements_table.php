<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Store scoped isolation
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity'); // Amount changed
            $table->enum('type', ['Addition', 'Deduction']); // Addition (+) or Deduction (-)
            $table->string('reference_type'); // 'Manual', 'Sale', 'Purchase'
            $table->unsignedBigInteger('reference_id')->nullable(); // sale_id or purchase_id
            $table->string('reason'); // "Damaged Goods", "Theft/Loss", "Sale Invoice #...", etc.
            $table->text('remarks')->nullable(); // Extended details
            $table->timestamps();

            // Scoping & FK constraint
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
