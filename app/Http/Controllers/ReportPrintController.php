<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\BusinessFinanceService;
use App\Services\Reports\BalanceSheetReportService;
use App\Services\Reports\CustomerAgingReportService;
use App\Services\Reports\CustomerCollectionsReportService;
use App\Services\Reports\DailySummaryReportService;
use App\Services\Reports\DeadStockReportService;
use App\Services\Reports\IncomeExpenseReportService;
use App\Services\Reports\PaymentMethodReportService;
use App\Services\Reports\PriceHistoryReportService;
use App\Services\Reports\ProductSalesReportService;
use App\Services\Reports\ProfitAndLossReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StockLossesReportService;
use App\Services\Reports\StockValuationReportService;
use App\Services\Reports\VendorAgingReportService;
use App\Services\Reports\VendorPaymentReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ReportPrintController extends Controller
{
    private function formatPeriodText(ReportPeriod $period): string
    {
        if ($period->from && $period->to) {
            if ($period->from->isSameDay($period->to)) {
                return $period->from->format('d M Y');
            }
            return $period->from->format('d M Y') . ' — ' . $period->to->format('d M Y');
        }

        if ($period->from) {
            return 'From ' . $period->from->format('d M Y');
        }

        if ($period->to) {
            return 'Up to ' . $period->to->format('d M Y');
        }

        return 'All Time';
    }

    /**
     * Statement of Financial Position (Balance Sheet)
     */
    public function balanceSheet(Request $request, BalanceSheetReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $asOf = $request->query('as_of', $request->query('as_of_date'));
        $data = $service->generate($asOf);

        return view('reports.print.balance-sheet', [
            'data' => $data,
            'title' => 'Statement of Financial Position (Balance Sheet)',
        ]);
    }

    /**
     * Sales & Collections Report (with Manager scoping)
     */
    public function sales(Request $request, SalesReportService $service): View
    {
        $isManager = ! $request->user()->isSuperAdmin();
        $visibility = Setting::get('manager_sales_visibility', 'all');

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = [];
        if ($isManager && $visibility === 'own') {
            $filters['cashier_id'] = $request->user()->id;
        } elseif ($request->filled('cashier_id')) {
            $filters['cashier_id'] = (int) $request->query('cashier_id');
        }

        if ($request->filled('payment_method')) {
            $filters['payment_method'] = (string) $request->query('payment_method');
        }
        if ($request->filled('customer_id')) {
            $filters['customer_id'] = (int) $request->query('customer_id');
        }

        $data = $service->generate($period, $filters, $request->user());

        return view('reports.print.sales', [
            'title' => 'Sales & Collections Report',
            'periodText' => $this->formatPeriodText($period),
            'rows' => $data['rows'],
            'totals' => $data['summary'],
            'isManager' => $isManager,
        ]);
    }

    /**
     * Product Sales & Gross Profit Report
     */
    public function productSales(Request $request, ProductSalesReportService $service, BusinessFinanceService $financeService): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = [];
        if ($request->filled('category_id')) {
            $filters['category_id'] = (int) $request->query('category_id');
        }
        if ($request->filled('product_id')) {
            $filters['product_id'] = (int) $request->query('product_id');
        }

        $data = $service->generate($period, $filters);

        $bfGross = $financeService->grossProfit($period->from, $period->to);
        $diff = bcsub($data['summary']['reconciled_gross_profit'] ?? '0.00', $bfGross, 2);
        $matches = bccomp($diff, '0.00', 2) === 0;

        return view('reports.print.product-sales', [
            'title' => 'Product Sales & Gross Profit Report',
            'periodText' => $this->formatPeriodText($period),
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'diff' => $diff,
            'matches' => $matches,
        ]);
    }

    /**
     * Purchase & Supplier Bills Report
     */
    public function purchases(Request $request, PurchaseReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = [];
        if ($request->filled('vendor_id')) {
            $filters['vendor_id'] = (int) $request->query('vendor_id');
        }

        $data = $service->generate($period, $filters);
        $summary = $data['summary'] ?? [];
        $totals = [
            'total_subtotal' => $summary['subtotal'] ?? '0.00',
            'total_discount' => $summary['discount'] ?? '0.00',
            'total_shipping' => $summary['shipping_cost'] ?? '0.00',
            'total_purchases' => $summary['total_purchases'] ?? '0.00',
            'total_paid' => $summary['total_paid'] ?? '0.00',
            'total_due' => $summary['total_due'] ?? '0.00',
            'purchases_count' => $summary['purchases_count'] ?? 0,
        ];

        return view('reports.print.purchases', [
            'title' => 'Purchase & Supplier Bills Report',
            'periodText' => $this->formatPeriodText($period),
            'rows' => $data['rows'],
            'totals' => $totals,
        ]);
    }

    /**
     * Profit & Loss Statement
     */
    public function profitAndLoss(Request $request, ProfitAndLossReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $data = $service->generate([
            'start_date' => $period->fromDateString(),
            'end_date' => $period->toDateString(),
        ]);

        return view('reports.print.profit-and-loss', [
            'title' => 'Statement of Profit and Loss (Income Statement)',
            'periodText' => $this->formatPeriodText($period),
            'data' => $data,
        ]);
    }

    /**
     * Daily Financial & Operations Summary (A4 and 80mm POS thermal)
     */
    public function dailySummary(Request $request, DailySummaryReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $date = $request->query('date', now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString());
        $format = $request->query('format', 'a4');

        $data = $service->generate($date);

        if ($format === '80mm') {
            return view('reports.print.daily-summary-80mm', [
                'title' => 'Daily EOD Summary',
                'data' => $data,
                'format' => '80mm',
            ]);
        }

        return view('reports.print.daily-summary', [
            'title' => 'Daily Financial & Operations Summary',
            'data' => $data,
            'format' => 'a4',
        ]);
    }

    /**
     * Stock Valuation Report
     */
    public function stockValuation(Request $request, StockValuationReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $filters = [];
        if ($request->filled('category_id')) {
            $filters['category_id'] = (int) $request->query('category_id');
        }
        if ($request->filled('stock_status')) {
            $filters['stock_status'] = (string) $request->query('stock_status');
        }

        $data = $service->generate($filters);

        $rows = collect();
        foreach ($data['categories'] as $cat) {
            foreach ($cat['products'] as $p) {
                $p['category_name'] = $cat['category_name'];
                $p['product_name'] = $p['name'];
                $p['retail_value'] = $p['potential_retail_value'];
                $rows->push($p);
            }
        }

        $totals = [
            'total_stock_qty' => $data['totals']['total_qty'] ?? '0.000',
            'total_fifo_value' => $data['totals']['total_fifo_value'] ?? '0.00',
            'total_retail_value' => $data['totals']['total_retail_value'] ?? '0.00',
            'total_potential_profit' => $data['totals']['total_potential_profit'] ?? '0.00',
        ];

        return view('reports.print.stock-valuation', [
            'title' => 'Stock Valuation & Inventory Summary',
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /**
     * Customer Receivables & Aging Report
     */
    public function customerDue(Request $request, CustomerAgingReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $asOf = $request->query('as_of', now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString());
        $data = $service->generate($asOf);

        return view('reports.print.customer-due', [
            'title' => 'Customer Due & Aging Statement',
            'asOfDate' => $asOf,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Vendor Payables & Aging Report
     */
    public function vendorDue(Request $request, VendorAgingReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $asOf = $request->query('as_of', now()->setTimezone(config('app.timezone', 'Asia/Dhaka'))->toDateString());
        $data = $service->generate($asOf);

        return view('reports.print.vendor-due', [
            'title' => 'Vendor Payables & Aging Statement',
            'asOfDate' => $asOf,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Dead Stock & Slow Moving Inventory
     */
    public function deadStock(Request $request, DeadStockReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $days = (int) $request->query('days', Setting::get('dead_stock_days', 60));
        $filters = ['days' => $days];
        if ($request->filled('category_id')) {
            $filters['category_id'] = (int) $request->query('category_id');
        }

        $data = $service->generate($filters);

        return view('reports.print.dead-stock', [
            'title' => 'Dead Stock & Slow Moving Inventory Report',
            'periodText' => "No sales activity in the last {$days} days",
            'days' => $days,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Stock Losses & Shrinkage
     */
    public function stockLosses(Request $request, StockLossesReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $data = $service->generate([
            'start_date' => $period->fromDateString(),
            'end_date' => $period->toDateString(),
        ]);

        return view('reports.print.stock-losses', [
            'title' => 'Stock Losses & Shrinkage Adjustments',
            'periodText' => $this->formatPeriodText($period),
            'adjustments' => $data['adjustments'],
            'purchaseReturns' => $data['purchase_returns'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Customer Collections Report
     */
    public function customerCollections(Request $request, CustomerCollectionsReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $data = $service->generate([
            'start_date' => $period->fromDateString(),
            'end_date' => $period->toDateString(),
        ]);

        return view('reports.print.customer-collections', [
            'title' => 'Customer Due Collections Register',
            'periodText' => $this->formatPeriodText($period),
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Vendor Payments Report
     */
    public function vendorPayments(Request $request, VendorPaymentReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $data = $service->generate([
            'start_date' => $period->fromDateString(),
            'end_date' => $period->toDateString(),
        ]);

        return view('reports.print.vendor-payments', [
            'title' => 'Vendor Payments & Bill Settlements',
            'periodText' => $this->formatPeriodText($period),
            'rows' => $data['rows'],
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Payment Methods & Gateway Flow
     */
    public function paymentMethod(Request $request, PaymentMethodReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $data = $service->generate($period);

        return view('reports.print.payment-method', [
            'title' => 'Payment Method & Gateway Liquidity Flow',
            'periodText' => $this->formatPeriodText($period),
            'methods' => $data['methods'],
            'accounts' => $data['by_account'],
            'totalInflow' => $data['total_inflow'],
            'totalNet' => $data['total_net'],
        ]);
    }

    /**
     * Income & Expense Breakdown
     */
    public function incomeExpense(Request $request, IncomeExpenseReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $period = ReportPeriod::fromPreset(
            (string) $request->query('period', 'this_month'),
            $request->query('from'),
            $request->query('to')
        );

        $data = $service->generate([
            'start_date' => $period->fromDateString(),
            'end_date' => $period->toDateString(),
        ]);

        $incomes = $data['categories']->where('category_type', 'income')->values();
        $expenses = $data['categories']->where('category_type', 'expense')->values();

        return view('reports.print.income-expense', [
            'title' => 'Operating Income & Expense Breakdown',
            'periodText' => $this->formatPeriodText($period),
            'incomes' => $incomes,
            'expenses' => $expenses,
            'totals' => $data['totals'],
        ]);
    }

    /**
     * Price History Audit
     */
    public function priceHistory(Request $request, PriceHistoryReportService $service): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $filters = [];
        if ($request->filled('product_id')) {
            $filters['product_id'] = (int) $request->query('product_id');
        }

        $data = $service->generate($filters);
        $rows = $data['price_changes'] ?? $data['rows'] ?? collect();

        return view('reports.print.price-history', [
            'title' => 'Product Price & Landed Cost Audit Trail',
            'rows' => $rows,
        ]);
    }

    /**
     * Audit Log
     */
    public function auditLog(Request $request): View
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $logs = Activity::query()
            ->with('causer')
            ->latest('id')
            ->limit(200)
            ->get();

        $rows = $logs->map(fn ($log) => [
            'date' => $log->created_at?->setTimezone(config('app.timezone', 'Asia/Dhaka'))->format('d M Y, h:i A') ?? '—',
            'causer_name' => $log->causer?->name ?? 'System',
            'description' => $log->description,
            'event' => $log->event,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
        ]);

        return view('reports.print.audit-log', [
            'title' => 'System Activity & Security Audit Log',
            'rows' => $rows,
        ]);
    }
}
