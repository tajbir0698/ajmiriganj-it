<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LedgerResult;
use App\DTOs\LedgerRow;
use App\DTOs\RecordTransactionData;
use App\Enums\AccountCategoryType;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\VendorPayment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AccountService
{
    /**
     * Record a transaction. This is the single writer of transactions.
     *
     * @throws InsufficientFundsException
     * @throws InvalidArgumentException
     */
    public function record(RecordTransactionData $data): Transaction
    {
        return DB::transaction(function () use ($data): Transaction {
            // 1. Validate Account
            /** @var Account|null $account */
            $account = Account::find($data->accountId);
            if (! $account) {
                throw new InvalidArgumentException("Account with ID {$data->accountId} not found.");
            }
            if (! $account->is_active) {
                throw new InvalidArgumentException("Account '{$account->name}' is deactivated and cannot accept transactions.");
            }

            // 2. Validate Category
            if ($data->categoryId !== null) {
                /** @var AccountCategory|null $category */
                $category = AccountCategory::find($data->categoryId);
                if (! $category) {
                    throw new InvalidArgumentException("Account category with ID {$data->categoryId} not found.");
                }
                if (! $category->is_active) {
                    throw new InvalidArgumentException("Account category '{$category->name}' is inactive.");
                }
            }

            // 3. Validate Amount (> 0)
            $amount = (string) $data->amount;
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw new InvalidArgumentException("Transaction amount must be strictly greater than 0. Received '{$amount}'.");
            }

            // 4. Validate Date (not in future in app timezone Asia/Dhaka)
            $dateStr = $data->date ?: now()->toDateString();
            $todayStr = now()->toDateString();
            if ($dateStr > $todayStr) {
                throw new InvalidArgumentException("Transaction date '{$dateStr}' cannot be in the future (today is {$todayStr}).");
            }

            $type = $data->type instanceof TransactionType ? $data->type : TransactionType::from((string) $data->type);
            $source = $data->source instanceof TransactionSource ? $data->source : TransactionSource::from((string) $data->source);

            // 5. Outflow / Insufficient funds check (Pessimistic lock)
            if ($type === TransactionType::OUT) {
                /** @var Account $lockedAccount */
                $lockedAccount = Account::where('id', $account->id)->lockForUpdate()->first();
                $currentBalance = $this->balance($lockedAccount);
                $resultingBalance = bcsub($currentBalance, $amount, 2);

                $allowNegative = (bool) Setting::get('allow_negative_balance', false);
                if (! $allowNegative && bccomp($resultingBalance, '0.00', 2) < 0) {
                    throw new InsufficientFundsException($lockedAccount, $currentBalance);
                }
            }

            // 6. Voucher Number for manual entries
            $voucherNo = $data->voucherNo;
            if (! $voucherNo && $source === TransactionSource::MANUAL) {
                $voucherNo = SequenceService::nextVoucherNo();
            }

            // 7. Persist Transaction
            return Transaction::create([
                'voucher_no' => $voucherNo,
                'date' => $dateStr,
                'account_id' => $account->id,
                'category_id' => $data->categoryId,
                'type' => $type,
                'source' => $source,
                'amount' => $amount,
                'reference_type' => $data->referenceType,
                'reference_id' => $data->referenceId,
                'party_type' => $data->partyType,
                'party_id' => $data->partyId,
                'description' => $data->description,
                'transfer_group_id' => $data->transferGroupId,
                'created_by' => $data->createdBy ?? auth()->id(),
            ]);
        });
    }

    /**
     * Transfer funds between two distinct accounts.
     *
     * @return array{0: Transaction, 1: Transaction}
     *
     * @throws InsufficientFundsException
     * @throws InvalidArgumentException
     */
    public function transfer(
        int $fromAccountId,
        int $toAccountId,
        string $amount,
        ?string $date = null,
        ?string $note = null,
        ?int $userId = null
    ): array {
        if ($fromAccountId === $toAccountId) {
            throw new InvalidArgumentException('Source and destination accounts must be distinct.');
        }

        return DB::transaction(function () use ($fromAccountId, $toAccountId, $amount, $date, $note, $userId): array {
            $transferGroupId = (string) Str::uuid();
            $dateStr = $date ?: now()->toDateString();

            $transferOutCat = AccountCategory::firstOrCreate(
                ['name' => 'Transfer Out'],
                ['type' => AccountCategoryType::TRANSFER, 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
            );

            $transferInCat = AccountCategory::firstOrCreate(
                ['name' => 'Transfer In'],
                ['type' => AccountCategoryType::TRANSFER, 'is_system' => true, 'is_active' => true, 'affects_profit' => false]
            );

            $outData = new RecordTransactionData(
                accountId: $fromAccountId,
                type: TransactionType::OUT,
                amount: $amount,
                categoryId: $transferOutCat->id,
                date: $dateStr,
                source: TransactionSource::TRANSFER,
                description: $note ?: 'Account transfer out',
                transferGroupId: $transferGroupId,
                createdBy: $userId ?? auth()->id()
            );

            $inData = new RecordTransactionData(
                accountId: $toAccountId,
                type: TransactionType::IN,
                amount: $amount,
                categoryId: $transferInCat->id,
                date: $dateStr,
                source: TransactionSource::TRANSFER,
                description: $note ?: 'Account transfer in',
                transferGroupId: $transferGroupId,
                createdBy: $userId ?? auth()->id()
            );

            $outTrx = $this->record($outData);
            $inTrx = $this->record($inData);

            return [$outTrx, $inTrx];
        });
    }

    /**
     * Reverse a transaction. Manual entries can be reversed; system entries cannot.
     * Paired transfers are reversed together.
     *
     * @throws InsufficientFundsException
     * @throws InvalidArgumentException
     */
    public function reverse(Transaction $transaction, string $reason, ?int $userId = null, bool $allowSystem = false): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason, $userId, $allowSystem): Transaction {
            if ($transaction->isReversal()) {
                throw new InvalidArgumentException('Cannot reverse a transaction that is already a reversal.');
            }

            if ($transaction->isReversed()) {
                throw new InvalidArgumentException('This transaction has already been reversed.');
            }

            if ($transaction->source === TransactionSource::SYSTEM && ! $allowSystem) {
                throw new InvalidArgumentException('System-generated transactions cannot be reversed from Accounts. Undo them from their originating module.');
            }

            $currentUserId = $userId ?? auth()->id();

            // Handle paired transfer reversal
            if ($transaction->transfer_group_id && $transaction->source === TransactionSource::TRANSFER) {
                return $this->reverseTransfer($transaction, $reason, $currentUserId);
            }

            return $this->executeSingleReversal($transaction, $reason, $currentUserId);
        });
    }

    /**
     * Reverses both legs of a paired transfer.
     */
    protected function reverseTransfer(Transaction $transaction, string $reason, ?int $userId): Transaction
    {
        /** @var Collection<int, Transaction> $legs */
        $legs = Transaction::where('transfer_group_id', $transaction->transfer_group_id)
            ->whereNull('reversal_of_id')
            ->get();

        $mainReversal = null;
        $transferReversalGroup = (string) Str::uuid();

        foreach ($legs as $leg) {
            if ($leg->isReversed()) {
                continue;
            }

            $rev = $this->executeSingleReversal($leg, $reason, $userId, $transferReversalGroup);
            if ($leg->id === $transaction->id) {
                $mainReversal = $rev;
            }
        }

        return $mainReversal ?? $this->executeSingleReversal($transaction, $reason, $userId, $transferReversalGroup);
    }

    /**
     * Execute a single reversal row creation and link update.
     */
    protected function executeSingleReversal(
        Transaction $t,
        string $reason,
        ?int $userId,
        ?string $transferGroupId = null
    ): Transaction {
        $oppositeType = $t->type === TransactionType::IN ? TransactionType::OUT : TransactionType::IN;
        $voucherDesc = $t->voucher_no ?: "Trx #{$t->id}";

        $reversalData = new RecordTransactionData(
            accountId: $t->account_id,
            type: $oppositeType,
            amount: (string) $t->amount,
            categoryId: $t->category_id,
            date: now()->toDateString(),
            source: $t->source,
            description: "Reversal of {$voucherDesc}: {$reason}",
            partyType: $t->party_type,
            partyId: $t->party_id,
            referenceType: $t->reference_type,
            referenceId: $t->reference_id,
            transferGroupId: $transferGroupId ?: $t->transfer_group_id,
            voucherNo: $t->source === TransactionSource::MANUAL ? SequenceService::nextVoucherNo() : null,
            createdBy: $userId
        );

        $reversalTrx = $this->record($reversalData);

        // Link reversal
        $reversalTrx->reversal_of_id = $t->id;
        $reversalTrx->reversal_reason = $reason;
        $reversalTrx->save();

        $t->reversed_at = now();
        $t->reversed_by_id = $reversalTrx->id;
        $t->reversal_reason = $reason;
        $t->save();

        return $reversalTrx;
    }

    /**
     * Compute the balance of an account as of an optional date/time.
     */
    public function balance(Account|int $account, ?CarbonInterface $asOf = null): string
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);

        $query = Transaction::where('account_id', $account->id);

        if ($asOf) {
            $query->whereDate('date', '<=', $asOf->toDateString());
        }

        $sums = $query->selectRaw("
            COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
            COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
        ")->first();

        $in = (string) ($sums->total_in ?? '0.00');
        $out = (string) ($sums->total_out ?? '0.00');

        $opening = (string) $account->opening_balance;
        $withIn = bcadd($opening, $in, 2);

        return bcsub($withIn, $out, 2);
    }

    /**
     * Compute current balances for all accounts in one single query.
     *
     * @return Collection<int, string> Keyed by account_id
     */
    public function balances(): Collection
    {
        $accounts = Account::all();
        $accountMap = $accounts->keyBy('id');

        $transactionSums = Transaction::query()
            ->selectRaw("
                account_id,
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->groupBy('account_id')
            ->pluck('total_out', 'account_id')
            ->mapWithKeys(function ($out, $accountId) use ($accountMap) {
                // Fetch sums
                return [$accountId => true];
            });

        $detailedSums = DB::table('transactions')
            ->selectRaw("
                account_id,
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
            ")
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        $result = collect();

        foreach ($accounts as $acc) {
            $sums = $detailedSums->get($acc->id);
            $in = (string) ($sums->total_in ?? '0.00');
            $out = (string) ($sums->total_out ?? '0.00');
            $opening = (string) $acc->opening_balance;

            $bal = bcsub(bcadd($opening, $in, 2), $out, 2);
            $result->put($acc->id, $bal);
        }

        return $result;
    }

    /**
     * Generate an account ledger for an optional period.
     */
    public function ledger(Account|int $account, Carbon|string|null $from = null, Carbon|string|null $to = null): LedgerResult
    {
        $account = $account instanceof Account ? $account : Account::findOrFail($account);
        $from = is_string($from) ? Carbon::parse($from) : $from;
        $to = is_string($to) ? Carbon::parse($to) : $to;

        // 1. Calculate opening balance strictly before $from
        $openingBalance = (string) $account->opening_balance;
        if ($from) {
            $priorDate = $from->copy()->subDay()->toDateString();
            $priorSums = Transaction::where('account_id', $account->id)
                ->whereDate('date', '<=', $priorDate)
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as total_in,
                    COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as total_out
                ")->first();

            $priorIn = (string) ($priorSums->total_in ?? '0.00');
            $priorOut = (string) ($priorSums->total_out ?? '0.00');
            $openingBalance = bcsub(bcadd($openingBalance, $priorIn, 2), $priorOut, 2);
        }

        // 2. Query period transactions
        $query = Transaction::where('account_id', $account->id)
            ->with(['category', 'creator'])
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');

        if ($from) {
            $query->whereDate('date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('date', '<=', $to->toDateString());
        }

        $transactions = $query->get();

        $running = $openingBalance;
        $totalIn = '0.00';
        $totalOut = '0.00';
        $rows = collect();

        foreach ($transactions as $t) {
            $amount = (string) $t->amount;
            $inAmount = $t->type === TransactionType::IN ? $amount : '0.00';
            $outAmount = $t->type === TransactionType::OUT ? $amount : '0.00';

            if ($t->type === TransactionType::IN) {
                $running = bcadd($running, $amount, 2);
                $totalIn = bcadd($totalIn, $amount, 2);
            } else {
                $running = bcsub($running, $amount, 2);
                $totalOut = bcadd($totalOut, $amount, 2);
            }

            $partyName = null;
            if ($t->party_type && $t->party_id) {
                $partyName = $this->resolvePartyName($t->party_type, $t->party_id);
            }

            $referenceTitle = null;
            if ($t->reference_type && $t->reference_id) {
                $referenceTitle = $this->resolveReferenceTitle($t->reference_type, $t->reference_id);
            }

            $rows->push(new LedgerRow(
                id: $t->id,
                date: $t->date->toDateString(),
                voucherNo: $t->voucher_no,
                categoryName: $t->category?->name,
                description: $t->description,
                partyName: $partyName,
                inAmount: $inAmount,
                outAmount: $outAmount,
                runningBalance: $running,
                isReversed: $t->isReversed(),
                isReversal: $t->isReversal(),
                referenceType: $t->reference_type,
                referenceId: $t->reference_id,
                referenceTitle: $referenceTitle,
                source: $t->source->value,
                transaction: $t
            ));
        }

        return new LedgerResult(
            account: $account,
            from: $from?->toDateString(),
            to: $to?->toDateString(),
            openingBalance: $openingBalance,
            rows: $rows,
            totalIn: $totalIn,
            totalOut: $totalOut,
            closingBalance: $running
        );
    }

    protected function resolvePartyName(string $type, int $id): ?string
    {
        try {
            $model = app($type)->find($id);
            return $model?->name ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveReferenceTitle(string $type, int $id): ?string
    {
        try {
            $model = app($type)->find($id);
            if ($model instanceof Sale) {
                return "Sale #{$model->invoice_no}";
            }
            if ($model instanceof Purchase) {
                return "Purchase #{$model->invoice_no}";
            }
            if ($model instanceof VendorPayment) {
                return "Vendor Payment #{$model->id}";
            }
            return class_basename($type)." #{$id}";
        } catch (\Throwable) {
            return null;
        }
    }
}
