<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PurchaseReturn;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\SaleReturn;
use App\Models\Transaction;
use App\Models\Vendor;
use App\Services\CustomerAccountService;
use App\Services\VendorAccountService;
use Illuminate\Console\Command;
use LogicException;

class CheckDuesCommand extends Command
{
    protected $signature = 'dues:check';

    protected $description = 'Verify due invariants, transaction matches for returns/collections, and stock integrity';

    public function handle(
        VendorAccountService $vendorAccountService,
        CustomerAccountService $customerAccountService
    ): int {
        $this->info('Starting dues, refunds, and allocation integrity checks...');
        $problems = [];

        // 1. Vendor Due Invariant for every vendor
        $vendors = Vendor::all();
        foreach ($vendors as $vendor) {
            try {
                $vendorAccountService->assertDueInvariant($vendor);
            } catch (LogicException $e) {
                $problems[] = [
                    'check' => 'Vendor Due Invariant',
                    'identifier' => "Vendor #{$vendor->id} ({$vendor->name})",
                    'issue' => $e->getMessage(),
                ];
            }
        }

        // 2. Customer Due Invariant for every customer
        $customers = Customer::all();
        foreach ($customers as $customer) {
            try {
                $customerAccountService->assertDueInvariant($customer);
            } catch (LogicException $e) {
                $problems[] = [
                    'check' => 'Customer Due Invariant',
                    'identifier' => "Customer #{$customer->id} ({$customer->name})",
                    'issue' => $e->getMessage(),
                ];
            }
        }

        // 3. Refunds & collections have matching transactions
        // Customer Payments (active)
        $customerPayments = CustomerPayment::whereNull('reversed_at')->get();
        foreach ($customerPayments as $cp) {
            $trx = Transaction::where('reference_type', CustomerPayment::class)
                ->where('reference_id', $cp->id)
                ->where('type', 'in')
                ->whereNull('reversal_of_id')
                ->first();

            if (! $trx) {
                $problems[] = [
                    'check' => 'Customer Payment Transaction',
                    'identifier' => "CustomerPayment #{$cp->id}",
                    'issue' => "No matching IN transaction found for ৳ {$cp->amount}.",
                ];
            } elseif (bccomp((string) $trx->amount, (string) $cp->amount, 2) !== 0) {
                $problems[] = [
                    'check' => 'Customer Payment Transaction Amount',
                    'identifier' => "CustomerPayment #{$cp->id}",
                    'issue' => "Transaction amount ৳ {$trx->amount} does not match payment ৳ {$cp->amount}.",
                ];
            }
        }

        // Purchase Returns with refund_received_amount > 0
        $purchaseReturns = PurchaseReturn::where('refund_received_amount', '>', 0)->get();
        foreach ($purchaseReturns as $pr) {
            $trx = Transaction::where('party_type', Vendor::class)
                ->where('party_id', $pr->vendor_id)
                ->where('type', 'in')
                ->where('amount', $pr->refund_received_amount)
                ->whereNull('reversal_of_id')
                ->first();

            if (! $trx) {
                $problems[] = [
                    'check' => 'Purchase Return Refund Transaction',
                    'identifier' => "PurchaseReturn {$pr->return_no}",
                    'issue' => "No matching IN transaction found for refund received ৳ {$pr->refund_received_amount}.",
                ];
            }
        }

        // Sale Returns with cash_refund > 0
        $saleReturns = SaleReturn::where('cash_refund', '>', 0)->get();
        foreach ($saleReturns as $sr) {
            $trx = Transaction::where('description', 'like', "%{$sr->return_no}%")
                ->where('type', 'out')
                ->where('amount', $sr->cash_refund)
                ->whereNull('reversal_of_id')
                ->first();

            if (! $trx) {
                $problems[] = [
                    'check' => 'Sale Return Cash Refund Transaction',
                    'identifier' => "SaleReturn {$sr->return_no}",
                    'issue' => "No matching OUT transaction found for cash refund ৳ {$sr->cash_refund}.",
                ];
            }
        }

        // 4. Restored stock never exceeds sold quantities
        $invalidSaleItems = SaleItem::whereRaw('returned_qty > qty')->get();
        foreach ($invalidSaleItems as $si) {
            $problems[] = [
                'check' => 'Restored Stock Exceeds Sold Item',
                'identifier' => "SaleItem #{$si->id} ({$si->product_name})",
                'issue' => "returned_qty ({$si->returned_qty}) exceeds sold qty ({$si->qty}).",
            ];
        }

        $invalidBatches = SaleItemBatch::whereRaw('returned_qty > qty')->get();
        foreach ($invalidBatches as $sib) {
            $problems[] = [
                'check' => 'Restored Stock Exceeds Sold Batch',
                'identifier' => "SaleItemBatch #{$sib->id}",
                'issue' => "returned_qty ({$sib->returned_qty}) exceeds batch qty ({$sib->qty}).",
            ];
        }

        if (! empty($problems)) {
            $this->error('Dues integrity check FAILED with '.count($problems).' problem(s):');
            $this->table(['Check', 'Identifier', 'Issue'], $problems);

            return Command::FAILURE;
        }

        $this->info('All dues, return settlements, and stock invariants PASSED successfully.');

        return Command::SUCCESS;
    }
}
