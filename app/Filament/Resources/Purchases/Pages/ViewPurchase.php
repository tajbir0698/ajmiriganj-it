<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Purchase $record */
        $record = $this->getRecord();
        $isUntouched = $record->isUntouched();

        return [
            Action::make('cancelPurchase')
                ->label('Cancel Purchase')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => $record->isActive())
                ->disabled(! $isUntouched)
                ->tooltip(! $isUntouched ? 'Purchase cannot be cancelled because batches have already been consumed. Please process a purchase return instead.' : null)
                ->requiresConfirmation()
                ->modalHeading("Cancel Purchase {$record->invoice_no}")
                ->modalDescription('This will completely reverse stock additions, reverse payments, and cancel the invoice. This action cannot be undone.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Cancellation Reason')
                        ->required()
                        ->placeholder('e.g. Invoiced by mistake or supplier order cancelled'),
                ])
                ->action(function (array $data, PurchaseService $purchaseService): void {
                    /** @var Purchase $purchase */
                    $purchase = $this->getRecord();
                    $purchaseService->cancelPurchase($purchase, $data['reason'], auth()->user());

                    Notification::make()
                        ->title("Purchase {$purchase->invoice_no} cancelled successfully.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'note']);
                }),
        ];
    }
}
