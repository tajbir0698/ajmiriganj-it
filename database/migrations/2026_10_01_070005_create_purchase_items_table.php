<?php

declare(strict_types=1);

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
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('landed_unit_cost', 15, 4);
            $table->decimal('remaining_qty', 15, 3);
            $table->decimal('new_sale_price', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'remaining_qty'], 'idx_purchase_items_product_remaining');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
