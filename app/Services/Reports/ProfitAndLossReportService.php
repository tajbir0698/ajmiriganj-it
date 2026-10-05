<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountCategoryType;
use App\Models\AccountCategory;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Transaction;
use App\Services\BusinessFinanceService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProfitAndLossReportService
{
    public function __construct(
        protected BusinessFinanceService $businessFinanceService
    ) {}

    /**
     * @param  array{
     *     start_date?: ?string,
     *     end_date?: ?string
     * }  $filters
     * @return array{
     *     period: array{
     *         start_date: ?string,
     *         end_date: ?string
     *     },
     *     sales_summary: array{
     *         gross_sales: string,
     *         discounts: string,
     *         net_sales: string,
     *         sale_returns: string,
     *         effective_sales: string,
     *         cogs: string,
     *         gross_profit: string
     *     },
     *     other_income: array{
     *         categories: Collection<int, array{category_name: string, net_amount: string}>,
     *         total: string
     *     },
     *     operating_expenses: array{
     *         categories: Collection<int, array{category_name: string, net_amount: string}>,
     *         total: string
     *     },
     *     stock_losses: string,
     *     net_profit: string,
     *     reconciliation_check: bool
     * }
     */
    public function generate(array $filters = []): array
    {
        $from = ! empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $to = ! empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        // 1. Single source of truth figures
        $bfGrossProfit = $this->businessFinanceService->grossProfit($from, $to);
        $bfOtherIncome = $this->businessFinanceService->otherIncome($from, $to);
        $bfOperatingExpenses = $this->businessFinanceService->operatingExpenses($from, $to);
        $bfStockLosses = $this->businessFinanceService->stockLosses($from, $to);
        $bfNetProfit = $this->businessFinanceService->netProfit($from, $to);

        // 2. Sales components breakdown
        $salesQuery = Sale::query()->where('status', 'completed');
        if ($from) {
            $salesQuery->whereDate('sale_date', '>=', $from->toDateString());
        }
        if ($to) {
            $salesQuery->whereDate('sale_date', '<=', $to->toDateString());
        }

        $salesAgg = $salesQuery->selectRaw('
            COALESCE(SUM(subtotal), 0) as total_subtotal,
            COALESCE(SUM(discount), 0) as total_discount,
            COALESCE(SUM(total), 0) as total_net_sales,
            COALESCE(SUM(gross_profit), 0) as total_gross_profit,
            COALESCE(SUM(net_profit), 0) as total_profit
        ')->first();

        $grossSales = bcadd((string) ($salesAgg->total_subtotal ?? '0.00'), '0.00', 2);
        $discounts = bcadd((string) ($salesAgg->total_discount ?? '0.00'), '0.00', 2);
        $netSales = bcadd((string) ($salesAgg->total_net_sales ?? '0.00'), '0.00', 2);
        $totalGrossProfitFromSales = bcadd((string) ($salesAgg->total_gross_profit ?? '0.00'), '0.00', 2);
        $salesCost = bcsub($grossSales, $totalGrossProfitFromSales, 2);

        $returnsQuery = SaleReturn::query();
        if ($from) {
            $returnsQuery->whereDate('return_date', '>=', $from->toDateString());
        }
        if ($to) {
            $returnsQuery->whereDate('return_date', '<=', $to->toDateString());
        }

        $returnsAgg = $returnsQuery->selectRaw('
            COALESCE(SUM(refund_amount), 0) as total_refund,
            COALESCE(SUM(cost_restored), 0) as total_cost_restored,
            COALESCE(SUM(profit_reversed), 0) as total_profit_reversed
        ')->first();

        $saleReturnsTotal = bcadd((string) ($returnsAgg->total_refund ?? '0.00'), '0.00', 2);
        $returnCostRecovered = bcadd((string) ($returnsAgg->total_cost_restored ?? '0.00'), '0.00', 2);
        $effectiveSales = bcsub($netSales, $saleReturnsTotal, 2);
        $effectiveCogs = bcsub($salesCost, $returnCostRecovered, 2);

        // 3. Category breakdowns for other income
        $incomeCategories = AccountCategory::query()
            ->where('type', AccountCategoryType::INCOME->value)
            ->where('affects_profit', true)
            ->get();

        $incomeBreakdown = collect();
        foreach ($incomeCategories as $cat) {
            $txQuery = Transaction::query()->where('category_id', $cat->id);
            if ($from) {
                $txQuery->whereDate('date', '>=', $from->toDateString());
            }
            if ($to) {
                $txQuery->whereDate('date', '<=', $to->toDateString());
            }
            $sums = $txQuery->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as t_in,
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as t_out
            ")->first();

            $net = bcsub((string) ($sums->t_in ?? '0.00'), (string) ($sums->t_out ?? '0.00'), 2);
            if (bccomp($net, '0.00', 2) !== 0) {
                $incomeBreakdown->push([
                    'category_name' => $cat->name,
                    'net_amount' => $net,
                ]);
            }
        }

        // 4. Category breakdowns for operating expenses
        $expenseCategories = AccountCategory::query()
            ->where('type', AccountCategoryType::EXPENSE->value)
            ->where('affects_profit', true)
            ->get();

        $expenseBreakdown = collect();
        foreach ($expenseCategories as $cat) {
            $txQuery = Transaction::query()->where('category_id', $cat->id);
            if ($from) {
                $txQuery->whereDate('date', '>=', $from->toDateString());
            }
            if ($to) {
                $txQuery->whereDate('date', '<=', $to->toDateString());
            }
            $sums = $txQuery->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'out' THEN amount ELSE 0 END), 0) as t_out,
                COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE 0 END), 0) as t_in
            ")->first();

            $net = bcsub((string) ($sums->t_out ?? '0.00'), (string) ($sums->t_in ?? '0.00'), 2);
            if (bccomp($net, '0.00', 2) !== 0) {
                $expenseBreakdown->push([
                    'category_name' => $cat->name,
                    'net_amount' => $net,
                ]);
            }
        }

        // Check reconciliation: Net Profit = Gross Profit + Other Income - Operating Expenses - Stock Losses
        $calcNet = bcsub(
            bcadd($bfGrossProfit, $bfOtherIncome, 2),
            bcadd($bfOperatingExpenses, $bfStockLosses, 2),
            2
        );
        $reconciliationCheck = bccomp($calcNet, $bfNetProfit, 2) === 0;

        return [
            'period' => [
                'start_date' => $from ? $from->toDateString() : null,
                'end_date' => $to ? $to->toDateString() : null,
            ],
            'sales_summary' => [
                'gross_sales' => $grossSales,
                'discounts' => $discounts,
                'net_sales' => $netSales,
                'sale_returns' => $saleReturnsTotal,
                'effective_sales' => $effectiveSales,
                'cogs' => $effectiveCogs,
                'gross_profit' => $bfGrossProfit,
            ],
            'other_income' => [
                'categories' => $incomeBreakdown,
                'total' => $bfOtherIncome,
            ],
            'operating_expenses' => [
                'categories' => $expenseBreakdown,
                'total' => $bfOperatingExpenses,
            ],
            'stock_losses' => $bfStockLosses,
            'net_profit' => $bfNetProfit,
            'reconciliation_check' => $reconciliationCheck,
        ];
    }
}
