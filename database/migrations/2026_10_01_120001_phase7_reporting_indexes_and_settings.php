<?php

declare(strict_types=1);

use App\Models\Setting;
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
        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['sale_date', 'status'], 'sales_date_status_index');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->index(['purchase_date', 'status'], 'purchases_date_status_index');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->index(['date', 'account_id', 'category_id'], 'transactions_reporting_index');
        });

        // Seed dead_stock_days setting if not present
        if (! Setting::where('key', 'dead_stock_days')->exists()) {
            Setting::set('dead_stock_days', '60', 'integer');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_date_status_index');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropIndex('purchases_date_status_index');
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transactions_reporting_index');
        });

        Setting::where('key', 'dead_stock_days')->delete();
    }
};
