<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseReturns\Pages;

use App\Enums\PaymentMethod;
use App\Enums\ReturnSettlement;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Models\Account;
use App\Models\Purchase;
use App\Services\PurchaseReturnService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var PurchaseReturnService $service */
        $service = app(PurchaseReturnService::class);

        $purchase = Purchase::findOrFail($data['purchase_id']);
        $settlement = ReturnSettlement::from($data['settlement']);
        $refundAccount = ! empty($data['refund_account_id']) ? Account::find($data['refund_account_id']) : null;
        $refundMethod = ! empty($data['refund_payment_method']) ? PaymentMethod::from($data['refund_payment_method']) : null;
        $returnDate = ! empty($data['return_date']) ? Carbon::parse($data['return_date']) : now();

        try {
            return $service->createReturn(
                purchase: $purchase,
                items: $data['items'] ?? [],
                settlement: $settlement,
                refundAccount: $refundAccount,
                refundMethod: $refundMethod,
                reason: $data['reason'] ?? null,
                returnDate: $returnDate
            );
        } catch (\Throwable $e) {
            \Filament\Notifications\Notification::make()
                ->title('Cannot Process Return')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
