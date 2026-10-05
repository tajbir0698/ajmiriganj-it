<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AdjustmentType;
use App\Enums\BatchSource;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockOperationException;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        protected FifoStockService $fifoStockService
    ) {}

    /**
     * Record a stock adjustment (increase, decrease, or damage) inside a transaction.
     *
     * @param  array{product_id: int, type: string|AdjustmentType, qty: string|float|int, unit_cost?: string|float|int|null, reason: string, adjusted_at?: string|Carbon|null}  $data
     */
    public function adjust(array $data, ?User $user = null): StockAdjustment
    {
        return DB::transaction(function () use ($data, $user): StockAdjustment {
            /** @var Product $product */
            $product = Product::findOrFail((int) $data['product_id']);
            $type = is_string($data['type']) ? AdjustmentType::from($data['type']) : $data['type'];
            $qty = bcadd((string) $data['qty'], '0.000', 3);
            $reason = (string) $data['reason'];
            $adjustedAt = isset($data['adjusted_at']) ? Carbon::parse($data['adjusted_at']) : now();
            $adjustmentNo = SequenceService::nextAdjustmentNo();

            if (bccomp($qty, '0.000', 3) <= 0) {
                throw new InvalidStockOperationException("Adjustment quantity must be greater than zero, given [{$qty}].");
            }

            if ($type === AdjustmentType::INCREASE) {
                if (! isset($data['unit_cost']) || bccomp((string) $data['unit_cost'], '0.0000', 4) < 0) {
                    throw new InvalidStockOperationException('A valid non-negative unit cost is required for stock increase adjustments.');
                }

                $unitCost = bcadd((string) $data['unit_cost'], '0.0000', 4);
                $totalCost = bcmul($qty, $unitCost, 2);

                // Add FIFO batch via FifoStockService
                $batch = $this->fifoStockService->addBatch(
                    product: $product,
                    qty: $qty,
                    unitCost: $unitCost,
                    source: BatchSource::ADJUSTMENT,
                    batchDate: $adjustedAt,
                    reference: null,
                    user: $user
                );

                /** @var StockAdjustment $adjustment */
                $adjustment = StockAdjustment::create([
                    'adjustment_no' => $adjustmentNo,
                    'product_id' => $product->id,
                    'type' => $type,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'reason' => $reason,
                    'adjusted_at' => $adjustedAt->toDateString(),
                    'created_by' => $user?->id ?? auth()->id(),
                ]);

                // Update movement reference if needed
                $batch->stockMovements()->latest('id')->first()?->update([
                    'reference_type' => StockAdjustment::class,
                    'reference_id' => $adjustment->id,
                ]);

                return $adjustment;
            }

            // Decrease or Damage: consume FIFO
            $movementType = $type === AdjustmentType::DAMAGE
                ? StockMovementType::DAMAGE
                : StockMovementType::ADJUSTMENT;

            $adjustment = new StockAdjustment([
                'adjustment_no' => $adjustmentNo,
                'product_id' => $product->id,
                'type' => $type,
                'qty' => $qty,
                'reason' => $reason,
                'adjusted_at' => $adjustedAt->toDateString(),
                'created_by' => $user?->id ?? auth()->id(),
            ]);

            // Save first to get an ID for movement reference
            $adjustment->save();

            $consumptionResult = $this->fifoStockService->consume(
                product: $product,
                qty: $qty,
                type: $movementType,
                reference: $adjustment,
                user: $user
            );

            $adjustment->update([
                'unit_cost' => $consumptionResult->averageUnitCost,
                'total_cost' => $consumptionResult->totalCost,
            ]);

            return $adjustment;
        });
    }
}
