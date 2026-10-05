<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Drop foreign key before modifying column in MySQL
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropForeign(['purchase_id']);
            });
        }

        Schema::table('purchase_items', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->foreignId('purchase_id')->nullable()->change()->constrained('purchases');
            } else {
                $table->foreignId('purchase_id')->nullable()->change();
            }
            $table->string('source')->default('purchase')->after('purchase_id')->index();
            $table->date('batch_date')->nullable()->after('source')->index();
        });

        // Backfill existing rows (cross-database standard subquery compatible with MySQL & SQLite)
        DB::statement('
            UPDATE purchase_items
            SET batch_date = (SELECT purchase_date FROM purchases WHERE purchases.id = purchase_items.purchase_id),
                source = "purchase"
            WHERE batch_date IS NULL AND purchase_id IS NOT NULL
        ');

        // Set batch_date fallback to today if any null remained
        DB::statement('UPDATE purchase_items SET batch_date = CURRENT_DATE WHERE batch_date IS NULL');

        // Add the new composite index
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->date('batch_date')->nullable(false)->change();
            $table->index(['product_id', 'remaining_qty', 'batch_date', 'id'], 'idx_batches_fifo_lookup');
        });

        if ($driver === 'mysql') {
            Schema::table('purchase_items', function (Blueprint $table) {
                $table->dropIndex('idx_purchase_items_product_remaining');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('purchase_items', function (Blueprint $table) use ($driver) {
            if ($driver === 'mysql') {
                $table->index(['product_id', 'remaining_qty'], 'idx_purchase_items_product_remaining');
                $table->dropIndex('idx_batches_fifo_lookup');
                $table->dropForeign(['purchase_id']);
                $table->dropColumn(['source', 'batch_date']);
                $table->foreignId('purchase_id')->nullable(false)->change()->constrained('purchases')->cascadeOnDelete();
            } else {
                $table->dropIndex('idx_batches_fifo_lookup');
                $table->dropColumn(['source', 'batch_date']);
            }
        });
    }
};
