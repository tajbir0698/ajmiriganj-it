<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Account;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorPayment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailySummaryReportService
{
    /**
     * @return array{
     *     date: string,
     *     cashier_breakdown: Collection<int, array{
     *         user_id: int,
     *         user_name: string,
     *         sales_count: int,
     *         sales_total: string,
     *         paid_amount: string,
     *         due_amount: string
     *     }>,
     *     inflows: array{
     *         pos_sales_collected: string,
     *         pos_by_method: array<string, string>,
     *         customer_due_collections: string,
     *         customer_collections_by_method: array<string, string>,
     *         other_income: string,
     *         owner_investments: string,
     *         total_inflows: string
     *     },
     *     outflows: array{
     *         sale_return_refunds: string,
     *         vendor_payments: string,
     *         vendor_payments_by_method: array<string, string>,
     *         operating_expenses: string,
     *         owner_drawings: string,
     *         profit_withdrawals: string,
     *         total_outflows: string
     *     },
     *     net_cash_flow: string,
     *     accounts_summary: Collection<int, array{
     *         account_id: int,
     *         account_name: string,
     *         account_kind: string,
     *         opening_balance: string,
     *         inflow: string,
     *         outflow: string,
     *         closing_balance: string
     *     }>,
     *     stock_summary: array{
     *         items_sold_qty: string,
     *         items_returned_qty: string,
     *         items_purchased_qty: string,
     *         damage_loss_qty: string
     *     }
     * }
     */
    public function generate(?string $targetDate = null): array
    {
        $timezone = config('app.timezone', 'Asia/Dhaka');
        $dateObj = $targetDate ? Carbon::parse($targetDate, $timezone)->startOfDay() : now()->setTimezone($timezone)->startOfDay();
        $dateStr = $dateObj->format('Y-m-d');

        // 1. Sales & Cashier Breakdown
        $salesToday = Sale::query()
            ->whereDate('sale_date', $dateStr)
            ->where('status', SaleStatus::COMPLETED)
            ->with('creator')
            ->get();

        $cashierMap = [];
        $posSalesCollected = '0.00';
        $posByMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $posByMethod[$method->value] = '0.00';
        }

        foreach ($salesToday as $sale) {
            $userId = $sale->created_by;
            $userName = $sale->creator ? $sale->creator->name : 'N/A';

            if (! isset($cashierMap[$userId])) {
                $cashierMap[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'sales_count' => 0,
                    'sales_total' => '0.00',
                    'paid_amount' => '0.00',
                    'due_amount' => '0.00',
                ];
            }

            $cashierMap[$userId]['sales_count']++;
            $cashierMap[$userId]['sales_total'] = bcadd($cashierMap[$userId]['sales_total'], (string) $sale->total, 2);
            $cashierMap[$userId]['paid_amount'] = bcadd($cashierMap[$userId]['paid_amount'], (string) $sale->paid_amount, 2);
            $cashierMap[$userId]['due_amount'] = bcadd($cashierMap[$userId]['due_amount'], (string) $sale->due_amount, 2);

            $paid = (string) $sale->paid_amount;
            $posSalesCollected = bcadd($posSalesCollected, $paid, 2);
            $methodVal = $sale->payment_method->value;
            $posByMethod[$methodVal] = bcadd($posByMethod[$methodVal] ?? '0.00', $paid, 2);
        }

        // 2. Customer Due Collections
        $custPaymentsToday = CustomerPayment::query()
            ->whereDate('payment_date', $dateStr)
            ->whereNull('reversed_at')
            ->get();

        $custDueCollections = '0.00';
        $custByMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $custByMethod[$method->value] = '0.00';
        }

        foreach ($custPaymentsToday as $cp) {
            $amt = (string) $cp->amount;
            $custDueCollections = bcadd($custDueCollections, $amt, 2);
            $methodVal = $cp->payment_method->value;
            $custByMethod[$methodVal] = bcadd($custByMethod[$methodVal] ?? '0.00', $amt, 2);
        }

        // 3. Transactions on date (categorized flows)
        $txsToday = Transaction::query()
            ->with('category')
            ->whereDate('date', $dateStr)
            ->get();

        $otherIncome = '0.00';
        $ownerInvestments = '0.00';
        $operatingExpenses = '0.00';
        $ownerDrawings = '0.00';
        $profitWithdrawals = '0.00';

        foreach ($txsToday as $tx) {
            $catName = $tx->category ? $tx->category->name : '';
            $catType = $tx->category ? ($tx->category->type instanceof \BackedEnum ? $tx->category->type->value : (string) $tx->category->type) : '';
            $txType = $tx->type instanceof \BackedEnum ? $tx->type->value : (string) $tx->type;
            $affectsProfit = $tx->category ? (bool) $tx->category->affects_profit : false;
            $amt = (string) $tx->amount;

            if ($catName === 'Owner Investment') {
                $ownerInvestments = $txType === 'in' ? bcadd($ownerInvestments, $amt, 2) : bcsub($ownerInvestments, $amt, 2);
            } elseif ($catName === 'Owner Drawing') {
                $ownerDrawings = $txType === 'out' ? bcadd($ownerDrawings, $amt, 2) : bcsub($ownerDrawings, $amt, 2);
            } elseif ($catName === 'Profit Withdrawal') {
                $profitWithdrawals = $txType === 'out' ? bcadd($profitWithdrawals, $amt, 2) : bcsub($profitWithdrawals, $amt, 2);
            } elseif ($catType === 'income' && $affectsProfit) {
                $otherIncome = $txType === 'in' ? bcadd($otherIncome, $amt, 2) : bcsub($otherIncome, $amt, 2);
            } elseif ($catType === 'expense' && $affectsProfit) {
                $operatingExpenses = $txType === 'out' ? bcadd($operatingExpenses, $amt, 2) : bcsub($operatingExpenses, $amt, 2);
            }
        }

        // 4. Sale Return Refunds (Cash refunded to customers)
        $saleReturnsToday = SaleReturn::query()
            ->whereDate('return_date', $dateStr)
            ->get();

        $saleReturnRefunds = '0.00';
        foreach ($saleReturnsToday as $sr) {
            $saleReturnRefunds = bcadd($saleReturnRefunds, (string) $sr->cash_refund, 2);
        }

        // 5. Vendor Payments
        $vendorPaymentsToday = VendorPayment::query()
            ->whereDate('payment_date', $dateStr)
            ->whereNull('reversed_at')
            ->get();

        $vendorPaymentsTotal = '0.00';
        $vendorByMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $vendorByMethod[$method->value] = '0.00';
        }

        foreach ($vendorPaymentsToday as $vp) {
            $amt = (string) $vp->amount;
            $vendorPaymentsTotal = bcadd($vendorPaymentsTotal, $amt, 2);
            $methodVal = $vp->payment_method->value;
            $vendorByMethod[$methodVal] = bcadd($vendorByMethod[$methodVal] ?? '0.00', $amt, 2);
        }

        // Total Inflows & Outflows
        $totalInflows = bcadd(
            bcadd($posSalesCollected, $custDueCollections, 2),
            bcadd($otherIncome, $ownerInvestments, 2),
            2
        );

        $totalOutflows = bcadd(
            bcadd(bcadd($saleReturnRefunds, $vendorPaymentsTotal, 2), $operatingExpenses, 2),
            bcadd($ownerDrawings, $profitWithdrawals, 2),
            2
        );

        $netCashFlow = bcsub($totalInflows, $totalOutflows, 2);

        // 6. Accounts Opening vs Closing on this date
        $accounts = Account::where('is_active', true)->get();
        $accountsSummary = collect();

        foreach ($accounts as $acc) {
            // Opening balance as of start of day:
            // balance before dateStr = opening_balance + sum(in before dateStr) - sum(out before dateStr)
            $preIn = (string) Transaction::where('account_id', $acc->id)->whereDate('date', '<', $dateStr)->where('type', 'in')->sum('amount');
            $preOut = (string) Transaction::where('account_id', $acc->id)->whereDate('date', '<', $dateStr)->where('type', 'out')->sum('amount');
            $openingOfDay = bcsub(bcadd((string) $acc->opening_balance, $preIn, 2), $preOut, 2);

            $dayIn = (string) Transaction::where('account_id', $acc->id)->whereDate('date', $dateStr)->where('type', 'in')->sum('amount');
            $dayOut = (string) Transaction::where('account_id', $acc->id)->whereDate('date', $dateStr)->where('type', 'out')->sum('amount');
            $closingOfDay = bcsub(bcadd($openingOfDay, $dayIn, 2), $dayOut, 2);

            $accountsSummary->push([
                'account_id' => $acc->id,
                'account_name' => $acc->name,
                'account_kind' => $acc->type->value,
                'account_kind_label' => $acc->type->label(),
                'opening_balance' => $openingOfDay,
                'inflow' => $dayIn,
                'outflow' => $dayOut,
                'closing_balance' => $closingOfDay,
            ]);
        }

        // 7. Stock Movements Today
        $movements = StockMovement::query()
            ->whereDate('created_at', $dateStr)
            ->get();

        $soldQty = '0.000';
        $returnedQty = '0.000';
        $purchasedQty = '0.000';
        $damageQty = '0.000';

        foreach ($movements as $m) {
            $qty = (string) $m->qty;
            $type = is_object($m->type) ? $m->type->value : (string) $m->type;

            if ($type === 'sale') {
                $soldQty = bcadd($soldQty, $qty, 3);
            } elseif ($type === 'sale_return') {
                $returnedQty = bcadd($returnedQty, $qty, 3);
            } elseif ($type === 'purchase') {
                $purchasedQty = bcadd($purchasedQty, $qty, 3);
            } elseif (in_array($type, ['damage', 'decrease_adjustment', 'adjustment_loss'], true)) {
                $damageQty = bcadd($damageQty, $qty, 3);
            }
        }

        return [
            'date' => $dateStr,
            'cashier_breakdown' => collect(array_values($cashierMap)),
            'inflows' => [
                'pos_sales_collected' => $posSalesCollected,
                'pos_by_method' => $posByMethod,
                'customer_due_collections' => $custDueCollections,
                'customer_collections_by_method' => $custByMethod,
                'other_income' => $otherIncome,
                'owner_investments' => $ownerInvestments,
                'total_inflows' => $totalInflows,
            ],
            'outflows' => [
                'sale_return_refunds' => $saleReturnRefunds,
                'vendor_payments' => $vendorPaymentsTotal,
                'vendor_payments_by_method' => $vendorByMethod,
                'operating_expenses' => $operatingExpenses,
                'owner_drawings' => $ownerDrawings,
                'profit_withdrawals' => $profitWithdrawals,
                'total_outflows' => $totalOutflows,
            ],
            'net_cash_flow' => $netCashFlow,
            'accounts_summary' => $accountsSummary,
            'stock_summary' => [
                'items_sold_qty' => $soldQty,
                'items_returned_qty' => $returnedQty,
                'items_purchased_qty' => $purchasedQty,
                'damage_loss_qty' => $damageQty,
            ],
        ];
    }
}
