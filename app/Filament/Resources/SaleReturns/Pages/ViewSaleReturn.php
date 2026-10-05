<?php

declare(strict_types=1);

namespace App\Filament\Resources\SaleReturns\Pages;

use App\Filament\Resources\SaleReturns\SaleReturnResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSaleReturn extends ViewRecord
{
    protected static string $resource = SaleReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receipt')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('sale-returns.receipt', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
