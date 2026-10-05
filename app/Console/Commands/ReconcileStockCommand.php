<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:reconcile {--fix : Apply corrections when safe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit inventory for drift across cached stock, FIFO batches, and stock movements.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $this->info($fix ? 'Running stock reconciliation WITH --fix enabled...' : 'Running stock reconciliation (DRY RUN)...');

        $products = Product::orderBy('id')->get();
        $mismatches = [];
        $unfixedCount = 0;

        foreach ($products as $product) {
            $cachedStock = bcadd((string) $product->stock_qty, '0.000', 3);

            // Sum of open batches remaining_qty
            $batchSumRaw = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '>', 0)
                ->sum('remaining_qty');
            $batchQty = bcadd((string) $batchSumRaw, '0.000', 3);

            // Sum of all stock movements (signed)
            $movementSumRaw = StockMovement::where('product_id', $product->id)
                ->sum('qty');
            $movementQty = bcadd((string) $movementSumRaw, '0.000', 3);

            // Check (b) per batch consistency
            $batchHistoryMismatch = false;
            $allBatches = PurchaseItem::where('product_id', $product->id)->get();
            foreach ($allBatches as $batch) {
                // Find all movements linked to this batch that are NOT the creation movement
                // The creation movement has positive qty equal to batch original qty and matching source type
                $creationMovement = StockMovement::where('purchase_item_id', $batch->id)
                    ->where('qty', '>', 0)
                    ->orderBy('id', 'asc')
                    ->first();

                $subsequentMovementsSum = StockMovement::where('purchase_item_id', $batch->id)
                    ->when($creationMovement, fn ($q) => $q->where('id', '!=', $creationMovement->id))
                    ->sum('qty');

                $expectedRemaining = bcadd((string) $batch->qty, (string) $subsequentMovementsSum, 3);
                if (bccomp((string) $batch->remaining_qty, $expectedRemaining, 3) !== 0) {
                    $batchHistoryMismatch = true;
                    break;
                }
            }

            $hasCacheMismatch = bccomp($cachedStock, $batchQty, 3) !== 0 || bccomp($cachedStock, $movementQty, 3) !== 0;
            $batchesAgreeWithMovements = bccomp($batchQty, $movementQty, 3) === 0;

            if ($hasCacheMismatch || $batchHistoryMismatch) {
                $status = 'Unresolved';

                if ($fix && $batchesAgreeWithMovements && ! $batchHistoryMismatch) {
                    // Safe to fix cached stock from batches
                    DB::transaction(function () use ($product, $batchQty): void {
                        /** @var Product $locked */
                        $locked = Product::where('id', $product->id)->lockForUpdate()->first();
                        $locked->stock_qty = $batchQty;
                        $locked->save();
                    });
                    $status = 'Fixed';
                } else {
                    $unfixedCount++;
                    if (! $batchesAgreeWithMovements || $batchHistoryMismatch) {
                        $status = 'Batches Disagree (Manual Review)';
                    }
                }

                $mismatches[] = [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'cached_qty' => $cachedStock,
                    'batch_qty' => $batchQty,
                    'movement_qty' => $movementQty,
                    'status' => $status,
                ];
            }
        }

        if (empty($mismatches)) {
            $this->info('✓ All stock records are 100% reconciled across products, batches, and movements.');

            return 0;
        }

        $this->table(
            ['ID', 'SKU', 'Product', 'Cached Stock', 'Batch Total', 'Movement Total', 'Status'],
            $mismatches
        );

        if ($unfixedCount > 0) {
            $msg = "Stock reconciliation found {$unfixedCount} unresolved mismatch(es).";
            $this->error($msg);
            Log::warning($msg, ['mismatches' => $mismatches]);

            return 1;
        }

        $this->info('✓ All detected cache drifts were safely corrected.');

        return 0;
    }
}
