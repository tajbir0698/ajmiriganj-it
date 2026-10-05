<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountBalancesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getStats(): array
    {
        /** @var AccountService $accountService */
        $accountService = app(AccountService::class);

        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $accountIds = $accounts->pluck('id')->all();
        $balances = $accountService->balances($accountIds);

        $totalLiquidAssets = '0.00';
        $stats = [];

        foreach ($accounts as $account) {
            $bal = $balances[$account->id] ?? '0.00';
            $totalLiquidAssets = bcadd($totalLiquidAssets, $bal, 2);
            $isNegative = bccomp($bal, '0.00', 2) < 0;

            $stats[] = Stat::make($account->name, '৳ '.Money::format($bal))
                ->description($account->kind?->label() ?? 'Account')
                ->descriptionIcon($account->kind?->icon() ?? 'heroicon-o-banknotes')
                ->color($isNegative ? 'danger' : 'success')
                ->url(AccountResource::getUrl('view', ['record' => $account]));
        }

        $totalIsNegative = bccomp($totalLiquidAssets, '0.00', 2) < 0;

        array_unshift(
            $stats,
            Stat::make('Total Liquid Assets', '৳ '.Money::format($totalLiquidAssets))
                ->description('Combined balance of active accounts')
                ->descriptionIcon('heroicon-o-scale')
                ->color($totalIsNegative ? 'danger' : 'primary')
        );

        return $stats;
    }
}
