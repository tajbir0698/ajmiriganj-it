<?php

declare(strict_types=1);

namespace App\Filament\Resources\SaleReturns\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\SaleReturns\SaleReturnResource;
use App\Models\Account;
use App\Models\Sale;
use App\Services\SaleReturnService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSaleReturn extends CreateRecord
{
    protected static string $resource = SaleReturnResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var SaleReturnService $service */
        $service = app(SaleReturnService::class);

        $sale = Sale::findOrFail($data['sale_id']);
        $refundAccount = ! empty($data['refund_account_id']) ? Account::find($data['refund_account_id']) : null;
        $refundMethod = ! empty($data['refund_payment_method']) ? PaymentMethod::from($data['refund_payment_method']) : null;
        $returnDate = ! empty($data['return_date']) ? Carbon::parse($data['return_date']) : now();

        try {
            if (! empty($data['return_whole_sale'])) {
                return $service->returnWholeSale(
                    sale: $sale,
                    refundAccount: $refundAccount,
                    refundMethod: $refundMethod,
                    reason: $data['reason'] ?? 'Whole sale return',
                    returnDate: $returnDate
                );
            }

            return $service->createReturn(
                sale: $sale,
                items: $data['items'] ?? [],
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
