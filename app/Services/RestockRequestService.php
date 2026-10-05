<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RestockRequestStatus;
use App\Enums\RoleName;
use App\Models\Purchase;
use App\Models\RestockRequest;
use App\Models\RestockRequestItem;
use App\Models\User;
use DomainException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RestockRequestService
{
    public function __construct(
        protected PurchaseService $purchaseService,
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Manager submits a restock request.
     * Server-side: strictly allows only note/notes and items with product_id & qty_requested.
     * No cost, vendor, price, or payment fields are ever accepted from this flow.
     */
    public function createRequest(array $data, array $files = [], ?User $requester = null): RestockRequest
    {
        $requester = $requester ?? auth()->user();

        if (! $requester) {
            throw new DomainException('A valid authenticated user is required to submit a restock request.');
        }

        $itemsData = $data['items'] ?? [];
        if (empty($itemsData)) {
            throw new DomainException('At least one product item is required for a restock request.');
        }

        return DB::transaction(function () use ($data, $files, $requester, $itemsData): RestockRequest {
            $requestNo = SequenceService::nextRestockRequestNo();

            $restockRequest = RestockRequest::create([
                'request_no' => $requestNo,
                'requested_by' => $requester->id,
                'status' => RestockRequestStatus::PENDING,
                'note' => $data['note'] ?? $data['notes'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $qty = (string) ($item['qty_requested'] ?? $item['qty'] ?? '0');
                if (bccomp($qty, '0', 3) <= 0) {
                    continue;
                }

                RestockRequestItem::create([
                    'restock_request_id' => $restockRequest->id,
                    'product_id' => (int) $item['product_id'],
                    'qty_requested' => $qty,
                    'qty_approved' => null,
                ]);
            }

            if (! empty($files)) {
                $this->attachmentService->storeMany($files, $restockRequest, $requester);
            }

            activity()
                ->performedOn($restockRequest)
                ->causedBy($requester)
                ->log("submitted restock request {$restockRequest->request_no}");

            // Notify all Super Admins via Database Notification
            $superAdmins = User::role(RoleName::SUPER_ADMIN->value)->get();
            foreach ($superAdmins as $admin) {
                Notification::make()
                    ->title('New Restock Request')
                    ->body("{$requester->name} submitted restock request {$restockRequest->request_no}")
                    ->icon('heroicon-o-arrow-path')
                    ->warning()
                    ->actions([
                        Action::make('view')
                            ->label('View Request')
                            ->url("/admin/restock-requests/{$restockRequest->id}"),
                    ])
                    ->sendToDatabase($admin);
            }

            return $restockRequest->fresh(['items.product', 'attachments']);
        });
    }

    /**
     * Cancel a pending restock request.
     * Only pending requests can be cancelled.
     */
    public function cancelRequest(RestockRequest $request, ?User $user = null): RestockRequest
    {
        $user = $user ?? auth()->user();

        return DB::transaction(function () use ($request, $user): RestockRequest {
            /** @var RestockRequest $locked */
            $locked = RestockRequest::where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== RestockRequestStatus::PENDING) {
                throw new DomainException('Only pending restock requests can be cancelled.');
            }

            if ($user && ! $user->isSuperAdmin() && $locked->requested_by !== $user->id) {
                throw new DomainException('You can only cancel your own pending restock requests.');
            }

            $locked->status = RestockRequestStatus::CANCELLED;
            $locked->save();

            activity()
                ->performedOn($locked)
                ->causedBy($user)
                ->log("cancelled restock request {$locked->request_no}");

            return $locked;
        });
    }

    /**
     * Reject a pending restock request with a reason.
     * Only pending requests can be rejected.
     */
    public function rejectRequest(RestockRequest $request, string $reason, ?User $reviewer = null): RestockRequest
    {
        $reviewer = $reviewer ?? auth()->user();

        return DB::transaction(function () use ($request, $reason, $reviewer): RestockRequest {
            /** @var RestockRequest $locked */
            $locked = RestockRequest::where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== RestockRequestStatus::PENDING) {
                throw new DomainException('Only pending restock requests can be rejected.');
            }

            $locked->status = RestockRequestStatus::REJECTED;
            $locked->review_note = $reason;
            $locked->reviewed_by = $reviewer?->id;
            $locked->reviewed_at = now();
            $locked->save();

            activity()
                ->performedOn($locked)
                ->causedBy($reviewer)
                ->log("rejected restock request {$locked->request_no}: {$reason}");

            return $locked;
        });
    }

    /**
     * Atomically approve restock request and create the purchase:
     * - Row locks the restock request
     * - Verifies request is strictly PENDING
     * - Creates purchase via PurchaseService
     * - Links unique purchase_id
     * - Calculates qty_approved per product (0 for products removed from the purchase)
     * - Sets status=APPROVED, reviewed_by, reviewed_at
     * - All in ONE DB transaction; if purchase creation fails (e.g. InsufficientFundsException),
     *   the transaction rolls back completely and the request remains PENDING.
     */
    public function approveAndCreatePurchase(RestockRequest $request, array $purchaseData, array $files = [], ?User $reviewer = null): Purchase
    {
        $reviewer = $reviewer ?? auth()->user();

        return DB::transaction(function () use ($request, $purchaseData, $files, $reviewer): Purchase {
            /** @var RestockRequest $locked */
            $locked = RestockRequest::with('items')
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== RestockRequestStatus::PENDING) {
                throw new DomainException('Only pending restock requests can be approved.');
            }

            // 1. Create Purchase via PurchaseService (inherits all calculation & single-writer rules)
            $purchase = $this->purchaseService->createPurchase($purchaseData, $files, $reviewer);

            // 2. Map actual approved quantities per product
            $approvedQtys = [];
            foreach ($purchaseData['items'] ?? [] as $item) {
                $prodId = (int) $item['product_id'];
                $approvedQtys[$prodId] = bcadd(
                    $approvedQtys[$prodId] ?? '0',
                    (string) $item['qty'],
                    3
                );
            }

            // 3. Update request items: set qty_approved (0 for removed items)
            foreach ($locked->items as $reqItem) {
                $approved = $approvedQtys[$reqItem->product_id] ?? '0.000';
                $reqItem->qty_approved = $approved;
                $reqItem->save();
            }

            // 4. Update request status, link purchase_id, reviewed_by, reviewed_at
            $locked->purchase_id = $purchase->id;
            $locked->status = RestockRequestStatus::APPROVED;
            $locked->reviewed_by = $reviewer?->id;
            $locked->reviewed_at = now();
            $locked->save();

            activity()
                ->performedOn($locked)
                ->causedBy($reviewer)
                ->log("approved restock request {$locked->request_no} and created purchase {$purchase->invoice_no}");

            return $purchase;
        });
    }
}
