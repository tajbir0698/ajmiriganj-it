<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\AccountService;
use App\Services\BusinessFinanceService;
use App\Services\FifoStockService;
use App\Services\Reports\BalanceSheetReportService;
use App\Services\Reports\CustomerAgingReportService;
use App\Services\Reports\CustomerDueReportService;
use App\Services\Reports\ProductSalesReportService;
use App\Services\Reports\ProfitAndLossReportService;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StockValuationReportService;
use App\Services\Reports\VendorAgingReportService;
use App\Services\Reports\VendorDueReportService;
use Illuminate\Console\Command;

class CheckReportsCommand extends Command
{
    protected $signature = 'reports:check';

    protected $description = 'Verify cross-report reconciliations and reporting invariants';

    public function handle(
        BusinessFinanceService $businessFinanceService,
        SalesReportService $salesReportService,
        ProductSalesReportService $productSalesReportService,
        ProfitAndLossReportService $profitAndLossReportService,
        StockValuationReportService $stockValuationReportService,
        FifoStockService $fifoStockService,
        AccountService $accountService,
        BalanceSheetReportService $balanceSheetReportService,
        CustomerDueReportService $customerDueReportService,
        CustomerAgingReportService $customerAgingReportService,
        VendorDueReportService $vendorDueReportService,
        VendorAgingReportService $vendorAgingReportService
    ): int {
        $this->info('Starting cross-report reconciliation checks...');
        $problems = [];

        // 1. P&L Net Profit == BusinessFinanceService::netProfit()
        $pnl = $profitAndLossReportService->generate();
        $bfNet = $businessFinanceService->netProfit();
        if (bccomp($pnl['net_profit'], $bfNet, 2) !== 0) {
            $problems[] = [
                'check' => 'P&L vs BusinessFinanceService Net Profit',
                'expected' => $bfNet,
                'actual' => $pnl['net_profit'],
                'issue' => 'P&L report net profit does not match BusinessFinanceService::netProfit().',
            ];
        }

        $periodAll = ReportPeriod::fromPreset('all');

        // 2. Sales Report Net Profit == sum(sales.net_profit) - sum(sale_returns.profit_reversed)
        $salesReport = $salesReportService->generate($periodAll);
        $rawSalesProfit = (string) Sale::where('status', 'completed')->sum('net_profit');
        $rawReturnsProfitReversed = (string) SaleReturn::sum('profit_reversed');
        $expectedSalesProfit = bcsub($rawSalesProfit, $rawReturnsProfitReversed, 2);
        $actualSalesProfit = (string) ($salesReport['summary']['net_profit'] ?? '0.00');
        if (bccomp($actualSalesProfit, $expectedSalesProfit, 2) !== 0) {
            $problems[] = [
                'check' => 'Sales Report Net Profit vs Formula',
                'expected' => $expectedSalesProfit,
                'actual' => $actualSalesProfit,
                'issue' => 'Sales Report net_profit mismatch with sum(net_profit) - sum(profit_reversed).',
            ];
        }

        // 3. Product Sales Report grand total == P&L gross profit
        $productSalesReport = $productSalesReportService->generate($periodAll);
        $bfGross = $businessFinanceService->grossProfit();
        $actualProdGross = (string) ($productSalesReport['summary']['reconciled_gross_profit'] ?? '0.00');
        if (bccomp($actualProdGross, $bfGross, 2) !== 0) {
            $problems[] = [
                'check' => 'Product Sales Report Grand Total vs P&L Gross Profit',
                'expected' => $bfGross,
                'actual' => $actualProdGross,
                'issue' => 'Product Sales Report grand total profit does not match P&L gross profit.',
            ];
        }

        // 4. Stock valuation total == FifoStockService::stockValue()
        $stockReport = $stockValuationReportService->generate();
        $fifoStockValue = $fifoStockService->stockValue();
        if (bccomp($stockReport['totals']['total_fifo_value'], $fifoStockValue, 2) !== 0) {
            $problems[] = [
                'check' => 'Stock Valuation Report vs FifoStockService',
                'expected' => $fifoStockValue,
                'actual' => $stockReport['totals']['total_fifo_value'],
                'issue' => 'Stock valuation report total does not match FifoStockService::stockValue().',
            ];
        }

        // 5. Balance sheet cash assets == sum of active account balances
        $balanceSheet = $balanceSheetReportService->generate();
        $activeAccountBalances = $accountService->balances();
        $totalAccountBal = '0.00';
        foreach (\App\Models\Account::where('is_active', true)->pluck('id') as $accId) {
            $totalAccountBal = bcadd($totalAccountBal, (string) ($activeAccountBalances->get($accId) ?? '0.00'), 2);
        }
        if (bccomp($balanceSheet['assets']['cash_and_bank']['total'], $totalAccountBal, 2) !== 0) {
            $problems[] = [
                'check' => 'Balance Sheet Cash vs AccountService Balances',
                'expected' => $totalAccountBal,
                'actual' => $balanceSheet['assets']['cash_and_bank']['total'],
                'issue' => 'Balance sheet cash total does not match sum of active account balances.',
            ];
        }

        // 6. Balance Sheet difference == 0.00
        if (! $balanceSheet['is_balanced'] || bccomp($balanceSheet['difference'], '0.00', 2) !== 0) {
            $problems[] = [
                'check' => 'Balance Sheet Balance (Assets - Liabilities - Equity === 0.00)',
                'expected' => '0.00',
                'actual' => $balanceSheet['difference'],
                'issue' => "Balance sheet is out of balance by ৳ {$balanceSheet['difference']}.",
            ];
        }

        // 7. Customer Due Report total == Customer Aging Report total
        $custDueReport = $customerDueReportService->generate();
        $custAgingReport = $customerAgingReportService->generate();
        if (bccomp($custDueReport['totals']['current_due'], $custAgingReport['totals']['net_due'], 2) !== 0) {
            $problems[] = [
                'check' => 'Customer Due Report vs Customer Aging Report',
                'expected' => $custDueReport['totals']['current_due'],
                'actual' => $custAgingReport['totals']['net_due'],
                'issue' => 'Customer Due Report total does not match Customer Aging Report net due total.',
            ];
        }

        // 8. Vendor Due Report total == Vendor Aging Report total
        $vendorDueReport = $vendorDueReportService->generate();
        $vendorAgingReport = $vendorAgingReportService->generate();
        if (bccomp($vendorDueReport['totals']['net_due'], $vendorAgingReport['totals']['net_due'], 2) !== 0) {
            $problems[] = [
                'check' => 'Vendor Due Report vs Vendor Aging Report',
                'expected' => $vendorDueReport['totals']['net_due'],
                'actual' => $vendorAgingReport['totals']['net_due'],
                'issue' => 'Vendor Due Report net due does not match Vendor Aging Report net due total.',
            ];
        }

        if (count($problems) > 0) {
            $this->error('Cross-report reconciliation checks failed with '.count($problems).' issue(s):');
            $this->table(['Check', 'Expected', 'Actual', 'Issue'], $problems);

            return Command::FAILURE;
        }

        $this->info('All cross-report reconciliations passed successfully!');
        $this->line('  ✓ P&L Net Profit == BusinessFinanceService::netProfit() (৳ '.$pnl['net_profit'].')');
        $this->line('  ✓ Sales Report Net Profit matches completed sales minus returns (৳ '.$actualSalesProfit.')');
        $this->line('  ✓ Product Sales grand total == P&L gross profit (৳ '.$actualProdGross.')');
        $this->line('  ✓ Stock Valuation matches FifoStockService (৳ '.$stockReport['totals']['total_fifo_value'].')');
        $this->line('  ✓ Balance Sheet cash matches account balances (৳ '.$balanceSheet['assets']['cash_and_bank']['total'].')');
        $this->line('  ✓ Balance Sheet is exactly balanced (Assets: ৳ '.$balanceSheet['assets']['total_assets'].', Liab+Equity: ৳ '.$balanceSheet['total_liabilities_and_equity'].', Diff: ৳ '.$balanceSheet['difference'].')');
        $this->line('  ✓ Customer Dues and Aging match (৳ '.$custDueReport['totals']['current_due'].')');
        $this->line('  ✓ Vendor Dues and Aging match (৳ '.$vendorDueReport['totals']['current_due'].')');

        return Command::SUCCESS;
    }
}
