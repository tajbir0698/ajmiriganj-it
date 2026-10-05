<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SaleStatus;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\VendorPayment;
use App\Services\AccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckAccountsIntegrityCommand extends Command
{
    protected $signature = 'accounts:check';

    protected $description = 'Run comprehensive read-only integrity checks on accounts and transactions';

    public function handle(AccountService $accountService): int
    {
        $this->info('Starting accounts and financial integrity check...');
        $problems = [];

        // Check (a): every transfer_group_id has exactly one out and one in with equal amounts
        $transfers = Transaction::whereNotNull('transfer_group_id')
            ->whereNull('reversal_of_id')
            ->get()
            ->groupBy('transfer_group_id');

        foreach ($transfers as $groupId => $legs) {
            $inLegs = $legs->where('type', TransactionType::IN);
            $outLegs = $legs->where('type', TransactionType::OUT);

            if ($inLegs->count() !== 1 || $outLegs->count() !== 1) {
                $problems[] = [
                    'check' => 'Transfer Pairing',
                    'identifier' => "Group: {$groupId}",
                    'issue' => "Expected exactly 1 IN and 1 OUT leg. Found {$inLegs->count()} IN and {$outLegs->count()} OUT.",
                ];
                continue;
            }

            $inAmount = (string) $inLegs->first()->amount;
            $outAmount = (string) $outLegs->first()->amount;

            if (bccomp($inAmount, $outAmount, 2) !== 0) {
                $problems[] = [
                    'check' => 'Transfer Amount Mismatch',
                    'identifier' => "Group: {$groupId}",
                    'issue' => "IN amount (৳ {$inAmount}) does not match OUT amount (৳ {$outAmount}).",
                ];
            }
        }

        // Check (b): every reversal points to an existing original and equals its amount with opposite type
        $reversals = Transaction::whereNotNull('reversal_of_id')->get();
        foreach ($reversals as $rev) {
            /** @var Transaction|null $original */
            $original = Transaction::find($rev->reversal_of_id);
            if (! $original) {
                $problems[] = [
                    'check' => 'Orphan Reversal',
                    'identifier' => "Trx #{$rev->id}",
                    'issue' => "Points to non-existent original transaction ID #{$rev->reversal_of_id}.",
                ];
                continue;
            }

            if (bccomp((string) $rev->amount, (string) $original->amount, 2) !== 0) {
                $problems[] = [
                    'check' => 'Reversal Amount Mismatch',
                    'identifier' => "Trx #{$rev->id} (Original #{$original->id})",
                    'issue' => "Reversal amount (৳ {$rev->amount}) does not match original (৳ {$original->amount}).",
                ];
            }

            $expectedOpposite = $original->type === TransactionType::IN ? TransactionType::OUT : TransactionType::IN;
            if ($rev->type !== $expectedOpposite) {
                $problems[] = [
                    'check' => 'Reversal Type Mismatch',
                    'identifier' => "Trx #{$rev->id}",
                    'issue' => "Reversal type ({$rev->type->value}) is not opposite to original ({$original->type->value}).",
                ];
            }
        }

        // Check (c): every completed sale with paid_amount > 0 has transactions summing to its paid amount
        $sales = Sale::where('status', SaleStatus::COMPLETED)
            ->where('paid_amount', '>', 0)
            ->get();

        foreach ($sales as $sale) {
            $sumTrx = Transaction::where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->where('type', TransactionType::IN)
                ->whereNull('reversal_of_id')
                ->sum('amount');

            $allocTrx = \App\Models\CustomerPaymentAllocation::where('sale_id', $sale->id)
                ->whereHas('customerPayment', fn ($cp) => $cp->whereNull('reversed_at'))
                ->sum('amount');

            $refundOutTrx = \App\Models\SaleReturn::where('sale_id', $sale->id)->sum('cash_refund');

            $netTrx = bcsub(bcadd((string) $sumTrx, (string) $allocTrx, 2), (string) $refundOutTrx, 2);

            if (bccomp((string) $netTrx, (string) $sale->paid_amount, 2) !== 0) {
                $problems[] = [
                    'check' => 'Sale Payment Reconciliation',
                    'identifier' => "Sale #{$sale->invoice_no}",
                    'issue' => "Paid amount (৳ {$sale->paid_amount}) does not match net transaction sum (৳ {$netTrx}).",
                ];
            }
        }

        // Check (d): every vendor_payments row has a matching transaction
        $vendorPayments = VendorPayment::all();
        foreach ($vendorPayments as $vp) {
            $matchingTrx = Transaction::where('reference_type', VendorPayment::class)
                ->where('reference_id', $vp->id)
                ->where('type', TransactionType::OUT)
                ->first();

            if (! $matchingTrx) {
                $problems[] = [
                    'check' => 'Missing Vendor Payment Transaction',
                    'identifier' => "VendorPayment #{$vp->id}",
                    'issue' => "No matching OUT transaction found for payment of ৳ {$vp->amount}.",
                ];
            } elseif (bccomp((string) $matchingTrx->amount, (string) $vp->amount, 2) !== 0) {
                $problems[] = [
                    'check' => 'Vendor Payment Amount Mismatch',
                    'identifier' => "VendorPayment #{$vp->id}",
                    'issue' => "Transaction amount (৳ {$matchingTrx->amount}) differs from payment record (৳ {$vp->amount}).",
                ];
            }
        }

        // Check (e): no account has a negative balance unless allow_negative_balance is on
        $allowNegative = (bool) Setting::get('allow_negative_balance', false);
        if (! $allowNegative) {
            $balances = $accountService->balances();
            foreach ($balances as $accountId => $balance) {
                if (bccomp($balance, '0.00', 2) < 0) {
                    $acc = Account::find($accountId);
                    $problems[] = [
                        'check' => 'Negative Account Balance',
                        'identifier' => $acc ? "Account: {$acc->name}" : "Account ID: {$accountId}",
                        'issue' => "Negative balance detected: ৳ {$balance} (allow_negative_balance is OFF).",
                    ];
                }
            }
        }

        if (! empty($problems)) {
            $this->error('Integrity check FAILED with '.count($problems).' problem(s):');
            $this->table(['Check', 'Identifier', 'Issue'], $problems);

            Log::warning('Accounts integrity check failed with problems: '.json_encode($problems));

            return Command::FAILURE;
        }

        $this->info('All accounts and transactions integrity checks PASSED successfully.');

        $duesResult = $this->call('dues:check');

        return $duesResult === Command::SUCCESS ? Command::SUCCESS : Command::FAILURE;
    }
}
