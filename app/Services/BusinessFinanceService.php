<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountCategoryType;
use App\Models\AccountCategory;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessFinanceService
{
    /**
     * Gross profit is the sum of completed sales net_profit (after bill discounts)
     * minus profit_reversed from sale returns.
     * Sales count on an accrual basis: a sale counts when made, even if the customer has not yet paid.
     * Returns count on their return_date in date-range queries.
     */
    public function grossProfit(?Carbon $from = null, ?Carbon $to = null): string
    {
        $salesQuery = Sale::query()->where('status', 'completed');

        if ($from) {
            $salesQuery->whereDate('sale_date', '>=', $from->toDateString());
        }
        if ($to) {
            $salesQuery->whereDate('sale_date', '<=', $to->toDateString());
        }

        $salesProfit = (string) $salesQuery->sum('net_profit');

        $returnsQuery = SaleReturn::query();
        if ($from) {
            $returnsQuery->whereDate('return_date', '>=', $from->toDateString());
        }
        if ($to) {
            $returnsQuery->whereDate('return_date', '<=', $to->toDateString());
        }

        $profitReversed = (string) $returnsQuery->sum('profit_reversed');
        $gross = bcsub($salesProfit, $profitReversed, 2);

        return number_format((float) $gross, 2, '.', '');
    }

    /**
     * Operating expenses: Net sum of ALL transaction rows whose category has affects_profit = true and type = expense.
     * Original expenses (out) minus reversals (in) naturally nets out without filtering reversed_at.
     */
    public function operatingExpenses(?Carbon $from = null, ?Carbon $to = null): string
    {
        $query = Transaction::query()
            ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
            ->where('account_categories.type', AccountCategoryType::EXPENSE->value)
            ->where('account_categories.affects_profit', true);

        if ($from) {
            $query->whereDate('transactions.date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('transactions.date', '<=', $to->toDateString());
        }

        $sums = $query->selectRaw("
            COALESCE(SUM(CASE WHEN transactions.type = 'out' THEN transactions.amount ELSE 0 END), 0) as total_out,
            COALESCE(SUM(CASE WHEN transactions.type = 'in' THEN transactions.amount ELSE 0 END), 0) as total_in
        ")->first();

        $out = (string) ($sums->total_out ?? '0.00');
        $in = (string) ($sums->total_in ?? '0.00');

        $net = bcsub($out, $in, 2);

        return bccomp($net, '0.00', 2) > 0 ? $net : '0.00';
    }

    /**
     * Other income: Net sum of ALL transaction rows whose category has affects_profit = true and type = income.
     * (Excludes Sales Income and Due Collection since affects_profit = false for them).
     */
    public function otherIncome(?Carbon $from = null, ?Carbon $to = null): string
    {
        $query = Transaction::query()
            ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
            ->where('account_categories.type', AccountCategoryType::INCOME->value)
            ->where('account_categories.affects_profit', true);

        if ($from) {
            $query->whereDate('transactions.date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('transactions.date', '<=', $to->toDateString());
        }

        $sums = $query->selectRaw("
            COALESCE(SUM(CASE WHEN transactions.type = 'in' THEN transactions.amount ELSE 0 END), 0) as total_in,
            COALESCE(SUM(CASE WHEN transactions.type = 'out' THEN transactions.amount ELSE 0 END), 0) as total_out
        ")->first();

        $in = (string) ($sums->total_in ?? '0.00');
        $out = (string) ($sums->total_out ?? '0.00');

        $net = bcsub($in, $out, 2);

        return bccomp($net, '0.00', 2) > 0 ? $net : '0.00';
    }

    /**
     * Stock losses: Sum of total_cost of StockAdjustment::losses() (damage and decrease adjustments)
     * plus sum of purchase_returns.loss_amount (negative = gain).
     * Returns count on their return_date in date-range queries.
     */
    public function stockLosses(?Carbon $from = null, ?Carbon $to = null): string
    {
        $query = StockAdjustment::losses();

        if ($from) {
            $query->whereDate('adjusted_at', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('adjusted_at', '<=', $to->toDateString());
        }

        $adjustmentLosses = (string) $query->sum('total_cost');

        $purchaseReturnsQuery = PurchaseReturn::query();
        if ($from) {
            $purchaseReturnsQuery->whereDate('return_date', '>=', $from->toDateString());
        }
        if ($to) {
            $purchaseReturnsQuery->whereDate('return_date', '<=', $to->toDateString());
        }

        $purchaseReturnLosses = (string) $purchaseReturnsQuery->sum('loss_amount');
        $totalLosses = bcadd($adjustmentLosses, $purchaseReturnLosses, 2);

        return number_format((float) $totalLosses, 2, '.', '');
    }

    /**
     * Net profit = gross_profit + other_income - operating_expenses - stock_losses.
     */
    public function netProfit(?Carbon $from = null, ?Carbon $to = null): string
    {
        $gross = $this->grossProfit($from, $to);
        $other = $this->otherIncome($from, $to);
        $expenses = $this->operatingExpenses($from, $to);
        $losses = $this->stockLosses($from, $to);

        $totalIncome = bcadd($gross, $other, 2);
        $totalDeductions = bcadd($expenses, $losses, 2);

        return bcsub($totalIncome, $totalDeductions, 2);
    }

    /**
     * Net Owner Investment (all rows in category Owner Investment: in - out).
     */
    public function ownerInvestment(?Carbon $from = null, ?Carbon $to = null): string
    {
        return $this->netCategoryFlow('Owner Investment', 'in', $from, $to);
    }

    /**
     * Net Owner Drawings (all rows in category Owner Drawing: out - in).
     */
    public function ownerDrawings(?Carbon $from = null, ?Carbon $to = null): string
    {
        return $this->netCategoryFlow('Owner Drawing', 'out', $from, $to);
    }

    /**
     * Net Profit Withdrawals (all rows in category Profit Withdrawal: out - in).
     */
    public function profitWithdrawals(?Carbon $from = null, ?Carbon $to = null): string
    {
        return $this->netCategoryFlow('Profit Withdrawal', 'out', $from, $to);
    }

    /**
     * Retained Profit (all-time) = net_profit - profit_withdrawals.
     */
    public function retainedProfit(): string
    {
        $allTimeNet = $this->netProfit();
        $withdrawals = $this->profitWithdrawals();

        return bcsub($allTimeNet, $withdrawals, 2);
    }

    /**
     * Owner Capital = owner_investment - owner_drawings + retained_profit.
     */
    public function ownerCapital(): string
    {
        $investment = $this->ownerInvestment();
        $drawings = $this->ownerDrawings();
        $retained = $this->retainedProfit();

        $equity = bcsub($investment, $drawings, 2);

        return bcadd($equity, $retained, 2);
    }

    /**
     * Available Profit to Withdraw = max(retained_profit, 0).
     */
    public function availableProfitToWithdraw(): string
    {
        $retained = $this->retainedProfit();

        return bccomp($retained, '0.00', 2) > 0 ? $retained : '0.00';
    }

    /**
     * Helper to compute net flow for a given named category.
     */
    protected function netCategoryFlow(string $categoryName, string $primaryDirection, ?Carbon $from, ?Carbon $to): string
    {
        $query = Transaction::query()
            ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
            ->where('account_categories.name', $categoryName);

        if ($from) {
            $query->whereDate('transactions.date', '>=', $from->toDateString());
        }
        if ($to) {
            $query->whereDate('transactions.date', '<=', $to->toDateString());
        }

        $sums = $query->selectRaw("
            COALESCE(SUM(CASE WHEN transactions.type = 'in' THEN transactions.amount ELSE 0 END), 0) as total_in,
            COALESCE(SUM(CASE WHEN transactions.type = 'out' THEN transactions.amount ELSE 0 END), 0) as total_out
        ")->first();

        $in = (string) ($sums->total_in ?? '0.00');
        $out = (string) ($sums->total_out ?? '0.00');

        return $primaryDirection === 'in' ? bcsub($in, $out, 2) : bcsub($out, $in, 2);
    }
}
