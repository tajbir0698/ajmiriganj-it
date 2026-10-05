<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\ConsumptionResult;
use App\Enums\BatchSource;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidStockOperationException;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class FifoStockService
{
    /**
     * Ensure the service method is executing within an active database transaction.
     */
    protected function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('FifoStockService methods must be executed within an active database transaction.');
        }
    }

    /**
     * Create a new FIFO batch, record positive stock movement, and update product stock.
     */
    public function addBatch(
        Product $product,
        string $qty,
        string $unitCost,
        string|BatchSource $source,
        CarbonInterface $batchDate,
        ?Model $reference = null,
        ?int $purchaseId = null,
        ?string $newSalePrice = null,
        ?string $landedUnitCost = null,
        ?User $user = null
    ): PurchaseItem {
        $this->assertInTransaction();

        $batchSource = is_string($source) ? BatchSource::from($source) : $source;
        $effectiveCost = $landedUnitCost !== null ? bcadd($landedUnitCost, '0.0000', 4) : bcadd($unitCost, '0.0000', 4);
        $cleanQty = bcadd($qty, '0.000', 3);

        /** @var Product $lockedProduct */
        $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

        // 1. Create the batch
        /** @var PurchaseItem $batch */
        $batch = PurchaseItem::create([
            'purchase_id' => $purchaseId,
            'product_id' => $lockedProduct->id,
            'source' => $batchSource,
            'batch_date' => $batchDate->toDateString(),
            'qty' => $cleanQty,
            'unit_cost' => bcadd($unitCost, '0.0000', 4),
            'landed_unit_cost' => $effectiveCost,
            'remaining_qty' => $cleanQty,
            'new_sale_price' => $newSalePrice !== null ? bcadd($newSalePrice, '0.00', 2) : null,
        ]);

        // 2. Map source to stock movement type
        $movementType = match ($batchSource) {
            BatchSource::PURCHASE => StockMovementType::PURCHASE,
            BatchSource::OPENING => StockMovementType::OPENING,
            BatchSource::ADJUSTMENT => StockMovementType::ADJUSTMENT,
        };

        // 3. Write stock movement
        StockMovement::create([
            'product_id' => $lockedProduct->id,
            'purchase_item_id' => $batch->id,
            'type' => $movementType->value,
            'qty' => $cleanQty, // signed positive
            'unit_cost' => $effectiveCost,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $user?->id ?? auth()->id(),
            'created_at' => $batchDate,
        ]);

        // 4. Update cached product stock_qty
        $newStock = bcadd((string) $lockedProduct->stock_qty, $cleanQty, 3);
        $lockedProduct->stock_qty = $newStock;

        // 5. Requirement: For source=opening only, set last_cost if the product has none yet
        if ($batchSource === BatchSource::OPENING && ((float) $lockedProduct->last_cost <= 0.0000)) {
            $lockedProduct->last_cost = $effectiveCost;
        }

        $lockedProduct->save();

        return $batch;
    }

    /**
     * Consume stock in FIFO order across open batches.
     */
    public function consume(
        Product $product,
        string $qty,
        StockMovementType $type,
        ?Model $reference = null,
        ?User $user = null
    ): ConsumptionResult {
        $this->assertInTransaction();

        $requestedQty = bcadd($qty, '0.000', 3);

        if (bccomp($requestedQty, '0.000', 3) <= 0) {
            throw new InvalidStockOperationException("Requested quantity to consume must be greater than zero, given [{$qty}].");
        }

        /** @var Product $lockedProduct */
        $lockedProduct = Product::where('id', $product->id)->lockForUpdate()->firstOrFail();

        // Lock open batches in strict FIFO order: batch_date ASC, id ASC
        /** @var Collection<int, PurchaseItem> $batches */
        $batches = PurchaseItem::where('product_id', $lockedProduct->id)
            ->where('remaining_qty', '>', 0)
            ->orderBy('batch_date', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        $totalAvailable = '0.000';
        foreach ($batches as $b) {
            $totalAvailable = bcadd($totalAvailable, (string) $b->remaining_qty, 3);
        }

        if (bccomp($totalAvailable, $requestedQty, 3) < 0) {
            throw new InsufficientStockException($lockedProduct, $requestedQty, $totalAvailable);
        }

        $remainingToConsume = $requestedQty;
        $allocations = [];
        $totalCostExact = '0.00000000';

        foreach ($batches as $batch) {
            if (bccomp($remainingToConsume, '0.000', 3) <= 0) {
                break;
            }

            $batchRemaining = (string) $batch->remaining_qty;

            if (bccomp($batchRemaining, $remainingToConsume, 3) <= 0) {
                // Entire remaining batch consumed
                $consumedSlice = $batchRemaining;
                $batch->remaining_qty = '0.000';
            } else {
                // Partial batch consumed
                $consumedSlice = $remainingToConsume;
                $batch->remaining_qty = bcsub($batchRemaining, $consumedSlice, 3);
            }

            $batch->save();

            $sliceUnitCost = bcadd((string) $batch->landed_unit_cost, '0.0000', 4);
            $sliceCost = bcmul($consumedSlice, $sliceUnitCost, 8);
            $totalCostExact = bcadd($totalCostExact, $sliceCost, 8);

            // Record stock movement (signed negative)
            StockMovement::create([
                'product_id' => $lockedProduct->id,
                'purchase_item_id' => $batch->id,
                'type' => $type->value,
                'qty' => '-'.$consumedSlice,
                'unit_cost' => $sliceUnitCost,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);

            $allocations[] = [
                'purchase_item_id' => $batch->id,
                'qty' => $consumedSlice,
                'unit_cost' => $sliceUnitCost,
            ];

            $remainingToConsume = bcsub($remainingToConsume, $consumedSlice, 3);
        }

        // Decrement product cached stock
        $lockedProduct->stock_qty = bcsub((string) $lockedProduct->stock_qty, $requestedQty, 3);
        $lockedProduct->save();

        $roundedTotalCost = bcadd($totalCostExact, '0.00', 2);
        $averageUnitCost = bcdiv($totalCostExact, $requestedQty, 4);

        return new ConsumptionResult(
            allocations: $allocations,
            totalCost: $roundedTotalCost,
            averageUnitCost: $averageUnitCost,
            totalQty: $requestedQty
        );
    }

    /**
     * Consume multiple lines atomically with deadlock prevention and pre-flight validation.
     *
     * @param  array<string|int, array{product_id: int, qty: string}>  $lines
     * @return array<string|int, ConsumptionResult>
     */
    public function consumeMany(
        array $lines,
        StockMovementType $type,
        ?Model $reference = null,
        ?User $user = null
    ): array {
        $this->assertInTransaction();

        if (empty($lines)) {
            return [];
        }

        // 1. Collect and aggregate requested quantities per product
        $aggregatedReqs = [];
        foreach ($lines as $line) {
            $pid = (int) $line['product_id'];
            $lineQty = bcadd((string) $line['qty'], '0.000', 3);
            $aggregatedReqs[$pid] = bcadd($aggregatedReqs[$pid] ?? '0.000', $lineQty, 3);
        }

        // 2. Lock all involved products in ascending product_id order to prevent deadlocks
        $productIds = array_keys($aggregatedReqs);
        sort($productIds, SORT_NUMERIC);

        /** @var Collection<int, Product> $lockedProducts */
        $lockedProducts = Product::whereIn('id', $productIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // 3. Pre-flight availability check across all products
        $shortages = [];
        foreach ($productIds as $pid) {
            /** @var Product $prod */
            $prod = $lockedProducts->get($pid);
            $neededQty = $aggregatedReqs[$pid];

            $available = PurchaseItem::where('product_id', $pid)
                ->where('remaining_qty', '>', 0)
                ->lockForUpdate()
                ->sum('remaining_qty');

            $availableQty = bcadd((string) $available, '0.000', 3);

            if (bccomp($availableQty, $neededQty, 3) < 0) {
                $shortages[] = [
                    'product' => $prod,
                    'requested' => $neededQty,
                    'available' => $availableQty,
                ];
            }
        }

        // If any line is short, abort without making any changes
        if (! empty($shortages)) {
            throw new InsufficientStockException(shortages: $shortages);
        }

        // 4. Execute consumption for each line in caller order
        $results = [];
        foreach ($lines as $key => $line) {
            /** @var Product $prod */
            $prod = $lockedProducts->get((int) $line['product_id']);
            $results[$key] = $this->consume($prod, (string) $line['qty'], $type, $reference, $user);
        }

        return $results;
    }

    /**
     * Restore stock back into its original batches (for sales returns).
     * Accepts either an array of allocations or a SaleItem with returnQty.
     * Partial returns restore into the MOST RECENTLY consumed batch allocation first,
     * capped by sale_item_batches.qty - returned_qty.
     *
     * @param  array<int, array{purchase_item_id: int, qty: string}>|SaleItem  $allocationsOrSaleItem
     * @return array<int, array{sale_item_batch_id?: int, purchase_item_id: int, qty: string, unit_cost?: string}>
     */
    public function restore(
        array|SaleItem $allocationsOrSaleItem,
        StockMovementType $type = StockMovementType::SALE_RETURN,
        ?Model $reference = null,
        ?User $user = null,
        ?string $returnQty = null
    ): array {
        $this->assertInTransaction();

        if ($allocationsOrSaleItem instanceof SaleItem) {
            $saleItem = $allocationsOrSaleItem;
            $qtyToRestore = bcadd($returnQty ?? (string) $saleItem->returnableQty(), '0.000', 3);

            if (bccomp($qtyToRestore, '0.000', 3) <= 0) {
                return [];
            }

            $returnable = $saleItem->returnableQty();
            if (bccomp($qtyToRestore, $returnable, 3) > 0) {
                throw new InvalidStockOperationException(
                    "Cannot return {$qtyToRestore} units for item {$saleItem->product_name}. Only {$returnable} units returnable."
                );
            }

            // Restore into MOST RECENTLY consumed batch allocation first (ID DESC)
            $batches = \App\Models\SaleItemBatch::where('sale_item_id', $saleItem->id)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->get();

            $batchAllocations = [];
            $needed = $qtyToRestore;

            foreach ($batches as $sib) {
                if (bccomp($needed, '0.000', 3) <= 0) {
                    break;
                }

                $sibReturnable = $sib->returnableQty();
                if (bccomp($sibReturnable, '0.000', 3) <= 0) {
                    continue;
                }

                $slice = bccomp($sibReturnable, $needed, 3) <= 0 ? $sibReturnable : $needed;

                $sib->returned_qty = bcadd((string) $sib->returned_qty, $slice, 3);
                $sib->save();

                $batchAllocations[] = [
                    'sale_item_batch_id' => $sib->id,
                    'purchase_item_id' => $sib->purchase_item_id,
                    'qty' => $slice,
                    'unit_cost' => (string) $sib->unit_cost,
                ];

                $needed = bcsub($needed, $slice, 3);
            }

            $saleItem->returned_qty = bcadd((string) $saleItem->returned_qty, $qtyToRestore, 3);
            $saleItem->save();

            // Perform batch restoration to stock
            $this->performStockRestore($batchAllocations, $type, $reference, $user);

            return $batchAllocations;
        }

        $this->performStockRestore($allocationsOrSaleItem, $type, $reference, $user);

        return $allocationsOrSaleItem;
    }

    /**
     * Perform the actual stock and batch remaining_qty updates for restoration.
     *
     * @param  array<int, array{purchase_item_id: int, qty: string}>  $allocations
     */
    protected function performStockRestore(
        array $allocations,
        StockMovementType $type,
        ?Model $reference = null,
        ?User $user = null
    ): void {
        if (empty($allocations)) {
            return;
        }

        // Sort batch IDs to prevent deadlocks
        $batchIds = array_unique(array_column($allocations, 'purchase_item_id'));
        sort($batchIds, SORT_NUMERIC);

        /** @var Collection<int, PurchaseItem> $lockedBatches */
        $lockedBatches = PurchaseItem::whereIn('id', $batchIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Pre-validate that no allocation exceeds the original batch quantity
        $additionsPerBatch = [];
        foreach ($allocations as $alloc) {
            $bid = (int) $alloc['purchase_item_id'];
            $qty = bcadd((string) $alloc['qty'], '0.000', 3);
            $additionsPerBatch[$bid] = bcadd($additionsPerBatch[$bid] ?? '0.000', $qty, 3);
        }

        foreach ($additionsPerBatch as $bid => $totalRestoreQty) {
            /** @var PurchaseItem|null $b */
            $b = $lockedBatches->get($bid);
            if (! $b) {
                throw new InvalidStockOperationException("Cannot restore stock: Batch #{$bid} not found.");
            }

            $projectedRemaining = bcadd((string) $b->remaining_qty, $totalRestoreQty, 3);
            if (bccomp($projectedRemaining, (string) $b->qty, 3) > 0) {
                throw new InvalidStockOperationException(
                    "Restoring {$totalRestoreQty} to batch #{$bid} would exceed original batch quantity ({$b->qty}). Current remaining: {$b->remaining_qty}."
                );
            }
        }

        // Lock all affected products
        $productIds = $lockedBatches->pluck('product_id')->unique()->all();
        sort($productIds, SORT_NUMERIC);

        /** @var Collection<int, Product> $lockedProducts */
        $lockedProducts = Product::whereIn('id', $productIds)
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Apply restorations
        foreach ($allocations as $alloc) {
            $bid = (int) $alloc['purchase_item_id'];
            $restoreQty = bcadd((string) $alloc['qty'], '0.000', 3);

            /** @var PurchaseItem $batch */
            $batch = $lockedBatches->get($bid);
            /** @var Product $product */
            $product = $lockedProducts->get($batch->product_id);

            $batch->remaining_qty = bcadd((string) $batch->remaining_qty, $restoreQty, 3);
            $batch->save();

            // Record signed positive stock movement
            StockMovement::create([
                'product_id' => $product->id,
                'purchase_item_id' => $batch->id,
                'type' => $type->value,
                'qty' => $restoreQty, // signed positive
                'unit_cost' => $batch->landed_unit_cost,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'created_by' => $user?->id ?? auth()->id(),
                'created_at' => now(),
            ]);

            // Increment cached product stock_qty
            $product->stock_qty = bcadd((string) $product->stock_qty, $restoreQty, 3);
            $product->save();
        }
    }

    /**
     * Deduct stock for a purchase return from the specific purchase item batch.
     */
    public function deductPurchaseReturnStock(
        PurchaseItem $batch,
        string $qty,
        ?Model $reference = null,
        ?User $user = null
    ): void {
        $this->assertInTransaction();

        $returnQty = bcadd($qty, '0.000', 3);
        if (bccomp($returnQty, '0.000', 3) <= 0) {
            throw new InvalidStockOperationException('Return quantity must be greater than zero.');
        }

        /** @var PurchaseItem $lockedBatch */
        $lockedBatch = PurchaseItem::where('id', $batch->id)->lockForUpdate()->firstOrFail();
        /** @var Product $lockedProduct */
        $lockedProduct = Product::where('id', $lockedBatch->product_id)->lockForUpdate()->firstOrFail();

        if (bccomp((string) $lockedBatch->remaining_qty, $returnQty, 3) < 0) {
            throw new InvalidStockOperationException(
                "Cannot return {$returnQty} units from batch #{$lockedBatch->id}: only {$lockedBatch->remaining_qty} remaining in stock."
            );
        }

        $lockedBatch->remaining_qty = bcsub((string) $lockedBatch->remaining_qty, $returnQty, 3);
        $lockedBatch->save();

        StockMovement::create([
            'product_id' => $lockedProduct->id,
            'purchase_item_id' => $lockedBatch->id,
            'type' => StockMovementType::PURCHASE_RETURN->value,
            'qty' => '-'.$returnQty,
            'unit_cost' => $lockedBatch->landed_unit_cost,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $user?->id ?? auth()->id(),
            'created_at' => now(),
        ]);

        $lockedProduct->stock_qty = bcsub((string) $lockedProduct->stock_qty, $returnQty, 3);
        $lockedProduct->save();
    }

    /**
     * Reverse an untouched batch completely upon purchase cancellation.
     */
    public function reverseBatch(
        PurchaseItem $batch,
        StockMovementType $type,
        ?Model $reference = null,
        ?User $user = null
    ): void {
        $this->assertInTransaction();

        /** @var PurchaseItem $lockedBatch */
        $lockedBatch = PurchaseItem::where('id', $batch->id)->lockForUpdate()->firstOrFail();
        /** @var Product $lockedProduct */
        $lockedProduct = Product::where('id', $lockedBatch->product_id)->lockForUpdate()->firstOrFail();

        if (! $lockedBatch->isUntouched()) {
            throw new InvalidStockOperationException(
                "Cannot reverse batch #{$lockedBatch->id} for {$lockedProduct->name}: remaining quantity ({$lockedBatch->remaining_qty}) is less than initial quantity ({$lockedBatch->qty})."
            );
        }

        $batchQty = (string) $lockedBatch->qty;

        // Record reversing negative stock movement
        StockMovement::create([
            'product_id' => $lockedProduct->id,
            'purchase_item_id' => $lockedBatch->id,
            'type' => $type->value,
            'qty' => '-'.$batchQty,
            'unit_cost' => $lockedBatch->landed_unit_cost,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $user?->id ?? auth()->id(),
            'created_at' => now(),
        ]);

        // Zero the batch
        $lockedBatch->remaining_qty = '0.000';
        $lockedBatch->save();

        // Decrement cached product stock
        $lockedProduct->stock_qty = bcsub((string) $lockedProduct->stock_qty, $batchQty, 3);
        $lockedProduct->save();
    }

    /**
     * Get the total available stock across open batches for a product.
     */
    public function availableQty(Product $product): string
    {
        $sum = PurchaseItem::where('product_id', $product->id)
            ->where('remaining_qty', '>', 0)
            ->sum('remaining_qty');

        return bcadd((string) $sum, '0.000', 3);
    }

    /**
     * Get all open batches for a product ordered by FIFO.
     *
     * @return Collection<int, PurchaseItem>
     */
    public function openBatches(Product $product): Collection
    {
        return PurchaseItem::where('product_id', $product->id)
            ->where('remaining_qty', '>', 0)
            ->orderBy('batch_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * Calculate FIFO valuation (sum of remaining_qty * landed_unit_cost) at scale 2.
     */
    public function stockValue(?Product $product = null): string
    {
        $query = PurchaseItem::where('remaining_qty', '>', 0);

        if ($product) {
            $query->where('product_id', $product->id);
        }

        $batches = $query->get(['remaining_qty', 'landed_unit_cost']);

        $totalValue = '0.0000';
        foreach ($batches as $batch) {
            $lineValue = bcmul((string) $batch->remaining_qty, (string) $batch->landed_unit_cost, 4);
            $totalValue = bcadd($totalValue, $lineValue, 4);
        }

        return bcadd($totalValue, '0.00', 2);
    }
}
