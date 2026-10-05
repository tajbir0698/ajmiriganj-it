<?php

declare(strict_types=1);

namespace App\Filament\Resources\RestockRequests\Pages;

use App\Filament\Resources\RestockRequests\RestockRequestResource;
use App\Services\RestockRequestService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateRestockRequest extends CreateRecord
{
    protected static string $resource = RestockRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $files = $data['attachments'] ?? [];
        unset($data['attachments']);

        $allowedData = [
            'note' => $data['note'] ?? null,
            'items' => array_map(function ($item) {
                return [
                    'product_id' => (int) $item['product_id'],
                    'qty_requested' => (string) $item['qty_requested'],
                ];
            }, $data['items'] ?? []),
        ];

        try {
            return app(RestockRequestService::class)->createRequest($allowedData, $files, auth()->user());
        } catch (Throwable $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
