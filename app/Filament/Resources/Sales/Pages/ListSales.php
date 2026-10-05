<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openPos')
                ->label('Open POS Terminal')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary')
                ->url('/pos')
                ->openUrlInNewTab(false),
        ];
    }
}
