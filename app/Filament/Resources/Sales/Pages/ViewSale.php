<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sales\Pages;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Sale $sale */
        $sale = $this->getRecord();

        return [
            Action::make('reprint')
                ->label('Reprint Receipt')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->url(fn (): string => route('sales.receipt', ['sale' => $sale, 'reprint' => 1]))
                ->openUrlInNewTab(),
        ];
    }
}
