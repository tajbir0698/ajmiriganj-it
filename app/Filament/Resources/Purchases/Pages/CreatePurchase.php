<?php

declare(strict_types=1);

namespace App\Filament\Resources\Purchases\Pages;

use App\Exceptions\InsufficientFundsException;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\RestockRequest;
use App\Models\Setting;
use App\Services\PurchaseService;
use App\Services\RestockRequestService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    public ?int $restockRequestId = null;

    public function mount(): void
    {
        parent::mount();

        $reqId = request()->query('restock_request_id');
        if ($reqId && auth()->user()?->isSuperAdmin()) {
            $restockRequest = RestockRequest::with(['items.product'])->find($reqId);
            if ($restockRequest && $restockRequest->isPending()) {
                $this->restockRequestId = $restockRequest->id;

                $defaultMargin = (float) Setting::get('default_target_margin_percent', 25);
                $items = [];
                foreach ($restockRequest->items as $item) {
                    $lastCost = (float) ($item->product?->last_cost ?? 0);
                    $suggested = $lastCost > 0
                        ? round($lastCost * (1 + $defaultMargin / 100))
                        : (float) ($item->product?->sale_price ?? 0);

                    $items[] = [
                        'product_id' => $item->product_id,
                        'qty' => (float) $item->qty_requested,
                        'unit_cost' => $lastCost,
                        'target_margin' => $defaultMargin,
                        'new_sale_price' => $suggested,
                    ];
                }

                $this->form->fill([
                    'purchase_date' => now()->toDateString(),
                    'items' => $items,
                ]);

                Notification::make()
                    ->title('Restock Request Loaded')
                    ->body("Loaded items from request {$restockRequest->request_no}. Complete vendor, costs, and payment details to approve.")
                    ->info()
                    ->send();
            }
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $files = $data['bill_files'] ?? [];
        unset($data['bill_files']);

        try {
            if ($this->restockRequestId) {
                /** @var RestockRequest $restockRequest */
                $restockRequest = RestockRequest::findOrFail($this->restockRequestId);

                return app(RestockRequestService::class)->approveAndCreatePurchase(
                    $restockRequest,
                    $data,
                    $files,
                    auth()->user()
                );
            }

            return app(PurchaseService::class)->createPurchase($data, $files, auth()->user());
        } catch (InsufficientFundsException $e) {
            Notification::make()
                ->title('Insufficient Funds')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        } catch (DomainException $e) {
            Notification::make()
                ->title('Validation Error')
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
