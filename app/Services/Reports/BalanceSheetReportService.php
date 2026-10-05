<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Vendor;
use App\Services\AccountService;
use App\Services\BusinessFinanceService;
use App\Services\CustomerAccountService;
use App\Services\FifoStockService;
use App\Services\VendorAccountService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BalanceSheetReportService
{
    public function __construct(
        protected AccountService $accountService,
        protected CustomerAccountService $customerAccountService,
        protected VendorAccountService $vendorAccountService,
        protected FifoStockService $fifoStockService,
        protected BusinessFinanceService $businessFinanceService
    ) {}

    /**
     * @return array{
     *     as_of_date: string,
     *     assets: array{
     *         cash_and_bank: array{
     *             accounts: Collection<int, array{id: int, name: string, type: string, balance: string}>,
     *             total: string
     *         },
     *         accounts_receivable: string,
     *         vendor_advances: string,
     *         inventory: string,
     *         total_assets: string
     *     },
     *     liabilities: array{
     *         accounts_payable: string,
     *         customer_advances: string,
     *         total_liabilities: string
     *     },
     *     equity: array{
     *         owner_investment: string,
     *         owner_drawings: string,
     *         retained_profit: string,
     *         owner_capital: string,
     *         balancing_equity: string,
     *         total_equity: string
     *     },
     *     total_liabilities_and_equity: string,
     *     difference: string,
     *     is_balanced: bool
     * }
     */
    public function generate(?string $asOfDate = null): array
    {
        $timezone = config('app.timezone', 'Asia/Dhaka');
        $referenceDate = $asOfDate ? Carbon::parse($asOfDate, $timezone)->endOfDay() : now()->setTimezone($timezone)->endOfDay();
        $dateStr = $referenceDate->format('Y-m-d');

        // 1. ASSETS
        // 1a. Cash & Bank
        $accounts = Account::where('is_active', true)->get();
        $accountRows = collect();
        $totalCash = '0.00';

        foreach ($accounts as $account) {
            $bal = $this->accountService->balance($account, $referenceDate);
            $accountRows->push([
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type->value,
                'type_label' => $account->type->label(),
                'balance' => $bal,
            ]);
            $totalCash = bcadd($totalCash, $bal, 2);
        }

        // 1b. Customer Receivables & Customer Advances
        $customers = Customer::all();
        $totalReceivables = '0.00';
        $totalCustomerAdvances = '0.00';

        foreach ($customers as $c) {
            $due = $this->customerAccountService->getCurrentDue($c);
            if (bccomp($due, '0.00', 2) > 0) {
                $totalReceivables = bcadd($totalReceivables, $due, 2);
            }
            $adv = $this->customerAccountService->getTotalAdvance($c);
            if (bccomp($adv, '0.00', 2) > 0) {
                $totalCustomerAdvances = bcadd($totalCustomerAdvances, $adv, 2);
            }
        }

        // 1c. Inventory (FIFO valuation)
        $inventoryValue = $this->fifoStockService->stockValue();

        // 1d. Vendor Advances
        $vendors = Vendor::all();
        $totalPayables = '0.00';
        $totalVendorAdvances = '0.00';

        foreach ($vendors as $v) {
            $due = $this->vendorAccountService->getCurrentDue($v);
            if (bccomp($due, '0.00', 2) > 0) {
                $totalPayables = bcadd($totalPayables, $due, 2);
            }
            $adv = $this->vendorAccountService->getTotalAdvance($v);
            if (bccomp($adv, '0.00', 2) > 0) {
                $totalVendorAdvances = bcadd($totalVendorAdvances, $adv, 2);
            }
        }

        $totalAssets = bcadd(
            bcadd($totalCash, $totalReceivables, 2),
            bcadd($totalVendorAdvances, $inventoryValue, 2),
            2
        );

        // 2. LIABILITIES
        $totalLiabilities = bcadd($totalPayables, $totalCustomerAdvances, 2);

        // 3. EQUITY
        $ownerInvestment = $this->businessFinanceService->ownerInvestment(null, $referenceDate);
        $ownerDrawings = $this->businessFinanceService->ownerDrawings(null, $referenceDate);
        $allTimeNetProfit = $this->businessFinanceService->netProfit(null, $referenceDate);
        $profitWithdrawals = $this->businessFinanceService->profitWithdrawals(null, $referenceDate);
        $retainedProfit = bcsub($allTimeNetProfit, $profitWithdrawals, 2);

        $ownerCapital = bcadd(bcsub($ownerInvestment, $ownerDrawings, 2), $retainedProfit, 2);

        // 3b. Explicit Opening & Adjustment Equity Lines (No hidden plug)
        $openingAccountBalances = bcadd((string) Account::where('is_active', true)->sum('opening_balance'), '0.00', 2);

        $openingBatches = \App\Models\PurchaseItem::whereNull('purchase_id')
            ->where('source', '!=', \App\Enums\BatchSource::ADJUSTMENT)
            ->get();
        $openingStockBatches = '0.00';
        foreach ($openingBatches as $b) {
            $openingStockBatches = bcadd($openingStockBatches, bcmul((string) $b->qty, (string) $b->unit_cost, 2), 2);
        }

        $openingCustomerBalances = bcadd((string) Customer::sum('opening_balance'), '0.00', 2);
        $openingVendorBalances = bcadd((string) Vendor::sum('opening_balance'), '0.00', 2);
        $positiveStockAdjustments = bcadd((string) \App\Models\StockAdjustment::where('type', \App\Enums\AdjustmentType::INCREASE)->sum('total_cost'), '0.00', 2);

        $initialAndAdjustmentEquity = bcadd($openingAccountBalances, $openingStockBatches, 2);
        $initialAndAdjustmentEquity = bcadd($initialAndAdjustmentEquity, $openingCustomerBalances, 2);
        $initialAndAdjustmentEquity = bcsub($initialAndAdjustmentEquity, $openingVendorBalances, 2);
        $initialAndAdjustmentEquity = bcadd($initialAndAdjustmentEquity, $positiveStockAdjustments, 2);

        $totalEquity = bcadd($ownerCapital, $initialAndAdjustmentEquity, 2);

        $totalLiabilitiesAndEquity = bcadd($totalLiabilities, $totalEquity, 2);
        $difference = bcsub($totalAssets, $totalLiabilitiesAndEquity, 2);
        $isBalanced = bccomp($difference, '0.00', 2) === 0;

        return [
            'as_of_date' => $dateStr,
            'assets' => [
                'cash_and_bank' => [
                    'accounts' => $accountRows,
                    'total' => $totalCash,
                ],
                'accounts_receivable' => $totalReceivables,
                'vendor_advances' => $totalVendorAdvances,
                'inventory' => $inventoryValue,
                'total_assets' => $totalAssets,
            ],
            'liabilities' => [
                'accounts_payable' => $totalPayables,
                'customer_advances' => $totalCustomerAdvances,
                'total_liabilities' => $totalLiabilities,
            ],
            'equity' => [
                'owner_investment' => $ownerInvestment,
                'owner_drawings' => $ownerDrawings,
                'retained_profit' => $retainedProfit,
                'owner_capital' => $ownerCapital,
                'opening_account_balances' => $openingAccountBalances,
                'opening_stock_batches' => $openingStockBatches,
                'opening_customer_balances' => $openingCustomerBalances,
                'opening_vendor_balances' => $openingVendorBalances,
                'positive_stock_adjustments' => $positiveStockAdjustments,
                'initial_and_adjustment_equity' => $initialAndAdjustmentEquity,
                'total_equity' => $totalEquity,
            ],
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'difference' => $difference,
            'is_balanced' => $isBalanced,
        ];
    }
}
