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
        // 1. Alter account_categories table
        Schema::table('account_categories', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->index()->after('type');
            $table->boolean('is_active')->default(true)->index()->after('is_system');
            $table->boolean('affects_profit')->default(false)->index()->after('is_active');
        });

        // 2. Alter accounts table
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('account_number')->nullable()->after('type');
            $table->text('note')->nullable()->after('opening_balance');
        });

        // Normalize existing mobile_money to mobile_wallet
        DB::table('accounts')->where('type', 'mobile_money')->update(['type' => 'mobile_wallet']);

        // 3. Alter transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('voucher_no')->nullable()->unique()->after('id');
            $table->string('source')->default('manual')->index()->after('type');
            $table->dateTime('reversed_at')->nullable()->after('transfer_group_id');
            $table->foreignId('reversed_by_id')->nullable()->after('reversed_at')->constrained('transactions')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->after('reversed_by_id')->constrained('transactions')->restrictOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversal_of_id');

            $table->index(['account_id', 'date']);
        });

        // Backfill transaction source
        DB::table('transactions')->whereNotNull('reference_id')->update(['source' => 'system']);
        DB::table('transactions')->whereNull('reference_id')->update(['source' => 'manual']);

        // 4. Seed system & initial categories
        $systemCategories = [
            ['name' => 'Sales Income', 'type' => 'income', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Due Collection', 'type' => 'income', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Vendor Payment', 'type' => 'expense', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Owner Investment', 'type' => 'equity', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Owner Drawing', 'type' => 'equity', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Profit Withdrawal', 'type' => 'equity', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Transfer Out', 'type' => 'transfer', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
            ['name' => 'Transfer In', 'type' => 'transfer', 'is_system' => true, 'is_active' => true, 'affects_profit' => false],
        ];

        foreach ($systemCategories as $cat) {
            DB::table('account_categories')->updateOrInsert(
                ['name' => $cat['name']],
                $cat + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $editableCategories = [
            ['name' => 'Rent', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Salary', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Electricity', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Transport', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Internet/Phone', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Marketing', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Repairs', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Other Expense', 'type' => 'expense', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
            ['name' => 'Other Income', 'type' => 'income', 'is_system' => false, 'is_active' => true, 'affects_profit' => true],
        ];

        foreach ($editableCategories as $cat) {
            DB::table('account_categories')->updateOrInsert(
                ['name' => $cat['name']],
                $cat + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['reversed_by_id']);
            $table->dropForeign(['reversal_of_id']);
            $table->dropIndex(['account_id', 'date']);
            $table->dropColumn(['voucher_no', 'source', 'reversed_at', 'reversed_by_id', 'reversal_of_id', 'reversal_reason']);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['account_number', 'note']);
        });

        Schema::table('account_categories', function (Blueprint $table) {
            $table->dropColumn(['is_system', 'is_active', 'affects_profit']);
        });
    }
};
