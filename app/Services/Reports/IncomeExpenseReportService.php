<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountCategoryType;
use App\Models\AccountCategory;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class IncomeExpenseReportService
{
    /**
     * @param  array{
     *     start_date?: ?string,
     *     end_date?: ?string,
     *     category_id?: ?int,
     *     account_id?: ?int,
     *     type?: ?string
     * }  $filters
     * @return array{
     *     categories: Collection<int, array{
     *         category_id: int,
     *         category_name: string,
     *         category_type: string,
     *         affects_profit: bool,
     *         transactions: Collection<int, array{
     *             id: int,
     *             date: string,
     *             account_name: string,
     *             type: string,
     *             amount: string,
     *             description: string,
     *             is_reversal: bool
     *         }>,
     *         total_in: string,
     *         total_out: string,
     *         net_amount: string
     *     }>,
     *     totals: array{
     *         total_income: string,
     *         total_expense: string,
     *         net_cash_flow: string
     *     }
     * }
     */
    public function generate(array $filters = []): array
    {
        $startDate = ! empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : null;
        $endDate = ! empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : null;

        $catQuery = AccountCategory::query()->orderBy('type')->orderBy('name');

        if (! empty($filters['category_id'])) {
            $catQuery->where('id', $filters['category_id']);
        }

        if (! empty($filters['type'])) {
            $catQuery->where('type', $filters['type']);
        } else {
            // Default to income and expense types
            $catQuery->whereIn('type', [AccountCategoryType::INCOME->value, AccountCategoryType::EXPENSE->value]);
        }

        $categories = $catQuery->get();

        $resultCategories = collect();
        $overallTotalIncome = '0.00';
        $overallTotalExpense = '0.00';

        foreach ($categories as $category) {
            $txQuery = Transaction::query()
                ->with('account')
                ->where('category_id', $category->id)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc');

            if ($startDate) {
                $txQuery->whereDate('date', '>=', $startDate->toDateString());
            }
            if ($endDate) {
                $txQuery->whereDate('date', '<=', $endDate->toDateString());
            }
            if (! empty($filters['account_id'])) {
                $txQuery->where('account_id', $filters['account_id']);
            }

            $txs = $txQuery->get();

            if ($txs->isEmpty() && empty($filters['category_id'])) {
                continue;
            }

            $catIn = '0.00';
            $catOut = '0.00';
            $txRows = collect();

            foreach ($txs as $tx) {
                $amt = (string) $tx->amount;
                $isReversal = $tx->isReversal();

                if ($tx->type === 'in') {
                    $catIn = bcadd($catIn, $amt, 2);
                } else {
                    $catOut = bcadd($catOut, $amt, 2);
                }

                $txRows->push([
                    'id' => $tx->id,
                    'date' => is_string($tx->date) ? $tx->date : $tx->date->format('Y-m-d'),
                    'account_name' => $tx->account ? $tx->account->name : 'N/A',
                    'type' => $tx->type,
                    'amount' => $amt,
                    'description' => $tx->description,
                    'is_reversal' => $isReversal,
                ]);
            }

            // Net amount for income is (in - out), for expense is (out - in)
            $net = $category->type === AccountCategoryType::INCOME->value
                ? bcsub($catIn, $catOut, 2)
                : bcsub($catOut, $catIn, 2);

            if ($category->type === AccountCategoryType::INCOME->value) {
                $overallTotalIncome = bcadd($overallTotalIncome, $net, 2);
            } else {
                $overallTotalExpense = bcadd($overallTotalExpense, $net, 2);
            }

            $resultCategories->push([
                'category_id' => $category->id,
                'category_name' => $category->name,
                'category_type' => $category->type,
                'affects_profit' => (bool) $category->affects_profit,
                'transactions' => $txRows,
                'total_in' => $catIn,
                'total_out' => $catOut,
                'net_amount' => $net,
            ]);
        }

        $netCashFlow = bcsub($overallTotalIncome, $overallTotalExpense, 2);

        return [
            'categories' => $resultCategories,
            'totals' => [
                'total_income' => $overallTotalIncome,
                'total_expense' => $overallTotalExpense,
                'net_cash_flow' => $netCashFlow,
            ],
        ];
    }
}
