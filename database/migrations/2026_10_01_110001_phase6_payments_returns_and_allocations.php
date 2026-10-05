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
        // 1. Add fields to vendor_payments and customer_payments
        Schema::table('vendor_payments', function (Blueprint $table): void {
            $table->timestamp('reversed_at')->nullable()->index()->after('created_by');
            $table->text('reversal_reason')->nullable()->after('reversed_at');
        });

        Schema::table('customer_payments', function (Blueprint $table): void {
            $table->timestamp('reversed_at')->nullable()->index()->after('created_by');
            $table->text('reversal_reason')->nullable()->after('reversed_at');
        });

        // 2. Add returned_amount to purchases
        Schema::table('purchases', function (Blueprint $table): void {
            $table->decimal('returned_amount', 15, 2)->default(0.00)->after('paid_amount');
        });

        // 3. Add returned_amount, outstanding_due, return_status to sales
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('returned_amount', 15, 2)->default(0.00)->after('paid_amount');
            $table->decimal('outstanding_due', 15, 2)->default(0.00)->after('due_amount');
            $table->string('return_status', 32)->default('none')->index()->after('status');
        });

        // 4. Add returned_qty to sale_items and sale_item_batches
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('returned_qty', 15, 3)->default(0.000)->after('qty');
        });

        Schema::table('sale_item_batches', function (Blueprint $table): void {
            $table->decimal('returned_qty', 15, 3)->default(0.000)->after('qty');
        });

        // 5. Vendor payment allocations
        Schema::create('vendor_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained('vendor_payments')->cascadeOnDelete();
            // NULL purchase_id means allocated towards opening balance
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_advance_application')->default(false)->index();
            $table->timestamps();

            $table->index(['vendor_payment_id', 'purchase_id']);
        });

        // 6. Customer payment allocations
        Schema::create('customer_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
            // NULL sale_id means allocated towards opening balance
            $table->foreignId('sale_id')->nullable()->constrained('sales')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_advance_application')->default(false)->index();
            $table->timestamps();

            $table->index(['customer_payment_id', 'sale_id']);
        });

        // 7. Purchase returns
        Schema::create('purchase_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_no', 32)->unique();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->date('return_date')->index();
            $table->decimal('total_cost_removed', 15, 2);
            $table->decimal('credit_amount', 15, 2);
            $table->decimal('refund_received_amount', 15, 2)->default(0.00);
            $table->decimal('loss_amount', 15, 2)->default(0.00);
            $table->string('settlement', 32); // reduce_due_credit, refund_received
            $table->foreignId('refund_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('refund_payment_method', 32)->nullable();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // 8. Purchase return items
        Schema::create('purchase_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('landed_unit_cost', 15, 4);
            $table->timestamps();
        });

        // 9. Sale returns
        Schema::create('sale_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_no', 32)->unique();
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->date('return_date')->index();
            $table->decimal('refund_amount', 15, 2);
            $table->decimal('due_reduction', 15, 2)->default(0.00);
            $table->decimal('cash_refund', 15, 2)->default(0.00);
            $table->decimal('cost_restored', 15, 2)->default(0.00);
            $table->decimal('profit_reversed', 15, 2)->default(0.00);
            $table->foreignId('refund_account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('refund_payment_method', 32)->nullable();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // 10. Sale return items
        Schema::create('sale_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_refund', 15, 4);
            $table->decimal('line_refund', 15, 2);
            $table->decimal('line_cost_restored', 15, 2);
            $table->timestamps();
        });

        // 11. Sale return item batches
        Schema::create('sale_return_item_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_return_item_id')->constrained('sale_return_items')->cascadeOnDelete();
            $table->foreignId('sale_item_batch_id')->constrained('sale_item_batches')->restrictOnDelete();
            $table->foreignId('purchase_item_id')->constrained('purchase_items')->restrictOnDelete();
            $table->decimal('qty', 15, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();
        });

        // 12. Backfill existing data
        // Backfill sales.outstanding_due = due_amount
        DB::table('sales')->update([
            'outstanding_due' => DB::raw('due_amount'),
        ]);

        // Backfill vendor_payment_allocations from existing vendor_payments
        $now = now()->toDateTimeString();
        $vendorPayments = DB::table('vendor_payments')->get();
        foreach ($vendorPayments as $vp) {
            DB::table('vendor_payment_allocations')->insert([
                'vendor_payment_id' => $vp->id,
                'purchase_id' => $vp->purchase_id ?? null,
                'amount' => $vp->amount,
                'is_advance_application' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Backfill customer_payment_allocations from existing customer_payments
        $customerPayments = DB::table('customer_payments')->get();
        foreach ($customerPayments as $cp) {
            DB::table('customer_payment_allocations')->insert([
                'customer_payment_id' => $cp->id,
                'sale_id' => $cp->sale_id ?? null,
                'amount' => $cp->amount,
                'is_advance_application' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Backfill transactions for customer payments if missing (via AccountService)
        $catDueCollection = DB::table('account_categories')->where('name', 'Due Collection')->first();
        if ($catDueCollection) {
            $accountService = app(\App\Services\AccountService::class);
            foreach ($customerPayments as $cp) {
                $hasTrx = DB::table('transactions')
                    ->where('reference_type', 'App\\Models\\CustomerPayment')
                    ->where('reference_id', $cp->id)
                    ->exists();

                if (! $hasTrx && $cp->account_id) {
                    $accountService->record(new \App\DTOs\RecordTransactionData(
                        accountId: (int) $cp->account_id,
                        type: \App\Enums\TransactionType::IN,
                        amount: (string) $cp->amount,
                        categoryId: (int) $catDueCollection->id,
                        date: is_string($cp->payment_date) ? substr($cp->payment_date, 0, 10) : today()->toDateString(),
                        source: \App\Enums\TransactionSource::SYSTEM,
                        description: "Due collection payment {$cp->payment_no}",
                        partyType: \App\Models\Customer::class,
                        partyId: (int) $cp->customer_id,
                        referenceType: \App\Models\CustomerPayment::class,
                        referenceId: (int) $cp->id,
                        createdBy: $cp->created_by ? (int) $cp->created_by : null,
                    ));
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_item_batches');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('vendor_payment_allocations');

        Schema::table('sale_item_batches', function (Blueprint $table): void {
            $table->dropColumn('returned_qty');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn('returned_qty');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['returned_amount', 'outstanding_due', 'return_status']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('returned_amount');
        });

        Schema::table('customer_payments', function (Blueprint $table): void {
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });

        Schema::table('vendor_payments', function (Blueprint $table): void {
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};
