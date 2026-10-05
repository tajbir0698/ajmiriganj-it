<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\RestockRequestStatus;
use App\Models\RestockRequest;
use App\Services\DashboardWidgetRegistry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingRestockRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            $pendingCount = RestockRequest::where('status', RestockRequestStatus::PENDING)->count();

            return [
                Stat::make('Pending Restock Requests', (string) $pendingCount)
                    ->description($pendingCount > 0 ? 'Awaiting Super Admin review' : 'All requests processed')
                    ->descriptionIcon('heroicon-o-arrow-path')
                    ->color($pendingCount > 0 ? 'warning' : 'success')
                    ->url('/admin/restock-requests'),
            ];
        }

        // Manager view: strictly safe summary without any cost or vendor details
        $pendingCount = RestockRequest::where('requested_by', $user->id)
            ->where('status', RestockRequestStatus::PENDING)
            ->count();
        $approvedCount = RestockRequest::where('requested_by', $user->id)
            ->where('status', RestockRequestStatus::APPROVED)
            ->count();

        return [
            Stat::make('My Pending Restocks', (string) $pendingCount)
                ->description("{$approvedCount} requests approved")
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color($pendingCount > 0 ? 'warning' : 'gray')
                ->url('/admin/restock-requests'),
        ];
    }
}
