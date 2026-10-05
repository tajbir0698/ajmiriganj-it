<?php

declare(strict_types=1);

namespace App\Filament\Resources\RestockRequests\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\RestockRequests\RestockRequestResource;
use App\Models\RestockRequest;
use App\Services\RestockRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRestockRequest extends ViewRecord
{
    protected static string $resource = RestockRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve & Create Purchase')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin() && $this->getRecord()->isPending())
                ->url(fn (): string => PurchaseResource::getUrl('create', ['restock_request_id' => $this->getRecord()->id])),

            Action::make('reject')
                ->label('Reject Request')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin() && $this->getRecord()->isPending())
                ->form([
                    Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    /** @var RestockRequest $record */
                    $record = $this->getRecord();
                    app(RestockRequestService::class)->rejectRequest($record, $data['reason'], auth()->user());
                    Notification::make()
                        ->title('Restock Request Rejected')
                        ->warning()
                        ->send();
                    $this->refreshFormData(['status', 'review_note', 'reviewed_by', 'reviewed_at']);
                }),

            Action::make('cancel')
                ->label('Cancel Request')
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(function (): bool {
                    /** @var RestockRequest $record */
                    $record = $this->getRecord();

                    return $record->isPending() && (auth()->user()?->isSuperAdmin() || $record->requested_by === auth()->id());
                })
                ->action(function (): void {
                    /** @var RestockRequest $record */
                    $record = $this->getRecord();
                    app(RestockRequestService::class)->cancelRequest($record, auth()->user());
                    Notification::make()
                        ->title('Restock Request Cancelled')
                        ->info()
                        ->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
