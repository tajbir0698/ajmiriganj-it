<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\DashboardWidgetRegistry;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestSalesWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return DashboardWidgetRegistry::isWidgetVisibleForUser(static::class, auth()->user());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest 5 Sales')
            ->description('Most recent customer sales transactions.')
            ->query(
                Sale::query()
                    ->with(['customer', 'creator'])
                    ->latest('id')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('invoice_no')
                    ->label('Invoice')
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->dateTime('d M Y, h:i A'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->default('Walk-in Customer'),

                TextColumn::make('creator.name')
                    ->label('Cashier')
                    ->default('System'),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge(),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->weight('bold'),

                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->color('success'),

                TextColumn::make('due_amount')
                    ->label('Due')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => Money::format((string) $state))
                    ->color(fn ($state) => (float) $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
