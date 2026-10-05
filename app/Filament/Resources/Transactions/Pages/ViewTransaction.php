<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transactions\Pages;

use App\Enums\TransactionSource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use App\Services\AccountService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Transaction $record */
        $record = $this->getRecord();

        return [
            Action::make('reverse')
                ->label('Reverse Transaction')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => ! $record->isReversed() && ! $record->isReversal() && $record->source !== TransactionSource::SYSTEM)
                ->requiresConfirmation()
                ->modalHeading('Reverse Transaction')
                ->modalDescription('This will create an opposite compensatory transaction, restoring the account balance. Please state the reason for this reversal.')
                ->form([
                    Textarea::make('reason')
                        ->label('Reversal Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data, AccountService $accountService): void {
                    try {
                        /** @var Transaction $record */
                        $record = $this->getRecord();
                        $accountService->reverse($record, $data['reason']);
                        Notification::make()->title('Transaction reversed successfully')->success()->send();
                        $this->refreshFormData(['status', 'reversed_at', 'reversal_reason']);
                    } catch (\Throwable $e) {
                        Notification::make()->title('Failed to reverse transaction')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
