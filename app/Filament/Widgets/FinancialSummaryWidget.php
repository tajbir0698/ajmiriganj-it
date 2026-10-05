<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Vendor;
use App\Services\BusinessFinanceService;
use App\Services\CustomerAccountService;
use App\Services\DashboardWidgetRegistry;
use App\Services\FifoStockService;
use App\Services\VendorAccountService;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getStats(): array
    {
        $fifoService = app(FifoStockService::class);
        $stockValue = $fifoService->stockValue();

        $custService = app(CustomerAccountService::class);
        $customers = Customer::all();
        $totalCustomerDue = '0.00';
        foreach ($customers as $c) {
            $due = $custService->getCurrentDue($c);
            if (bccomp($due, '0.00', 2) > 0) {
                $totalCustomerDue = bcadd($totalCustomerDue, $due, 2);
            }
        }

        $vendorService = app(VendorAccountService::class);
        $vendors = Vendor::all();
        $totalVendorDue = '0.00';
        foreach ($vendors as $v) {
            $due = $vendorService->getCurrentDue($v);
            if (bccomp($due, '0.00', 2) > 0) {
                $totalVendorDue = bcadd($totalVendorDue, $due, 2);
            }
        }

        $financeService = app(BusinessFinanceService::class);
        $ownerCapital = $financeService->ownerCapital();

        return [
            Stat::make('Stock Inventory Value', '৳ '.Money::format($stockValue))
                ->description('FIFO valuation of available stock')
                ->descriptionIcon('heroicon-o-cube')
                ->color('primary'),

            Stat::make('Customer Receivables', '৳ '.Money::format($totalCustomerDue))
                ->description('Outstanding customer dues')
                ->descriptionIcon('heroicon-o-users')
                ->color('warning'),

            Stat::make('Vendor Payables', '৳ '.Money::format($totalVendorDue))
                ->description('Outstanding vendor bills')
                ->descriptionIcon('heroicon-o-truck')
                ->color('danger'),

            Stat::make('Owner Capital', '৳ '.Money::format($ownerCapital))
                ->description('Investment - Drawings + Retained Profit')
                ->descriptionIcon('heroicon-o-building-library')
                ->color('success'),
        ];
    }
}
