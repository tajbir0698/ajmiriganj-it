<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\PriceHistory;
use App\Models\PurchaseItem;
use Illuminate\Support\Collection;

class PriceHistoryReportService
{
    /**
     * @param  array{
     *     product_id?: ?int,
     *     start_date?: ?string,
     *     end_date?: ?string
     * }  $filters
     * @return array{
     *     price_changes: Collection<int, array{
     *         id: int,
     *         date: string,
     *         product_id: int,
     *         product_name: string,
     *         sku: string,
     *         old_cost: string,
     *         new_cost: string,
     *         cost_difference: string,
     *         old_sale_price: string,
     *         new_sale_price: string,
     *         sale_price_difference: string,
     *         reason: ?string,
     *         changed_by: string
     *     }>,
     *     vendor_comparison: Collection<int, array{
     *         product_id: int,
     *         product_name: string,
     *         vendor_id: int,
     *         vendor_name: string,
     *         last_purchase_date: string,
     *         latest_unit_cost: string,
     *         latest_landed_cost: string
     *     }>
     * }
     */
    public function generate(array $filters = []): array
    {
        $changeQuery = PriceHistory::query()
            ->with(['product', 'changedBy'])
            ->orderBy('changed_at', 'desc');

        if (! empty($filters['product_id'])) {
            $changeQuery->where('product_id', $filters['product_id']);
        }
        if (! empty($filters['start_date'])) {
            $changeQuery->whereDate('changed_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $changeQuery->whereDate('changed_at', '<=', $filters['end_date']);
        }

        $changes = $changeQuery->get();

        $priceChanges = collect();
        foreach ($changes as $item) {
            $oldCost = bcadd((string) $item->old_cost, '0.00', 2);
            $newCost = bcadd((string) $item->new_cost, '0.00', 2);
            $costDiff = bcsub($newCost, $oldCost, 2);

            $oldPrice = bcadd((string) $item->old_sale_price, '0.00', 2);
            $newPrice = bcadd((string) $item->new_sale_price, '0.00', 2);
            $priceDiff = bcsub($newPrice, $oldPrice, 2);

            $priceChanges->push([
                'id' => $item->id,
                'date' => is_string($item->changed_at) ? $item->changed_at : $item->changed_at->format('Y-m-d H:i'),
                'product_id' => $item->product_id,
                'product_name' => $item->product ? $item->product->name : 'N/A',
                'sku' => $item->product ? $item->product->sku : '',
                'old_cost' => $oldCost,
                'new_cost' => $newCost,
                'cost_difference' => $costDiff,
                'old_sale_price' => $oldPrice,
                'new_sale_price' => $newPrice,
                'sale_price_difference' => $priceDiff,
                'reason' => $item->reason,
                'changed_by' => $item->changedBy ? $item->changedBy->name : 'System',
            ]);
        }

        // Vendor purchase comparison per product
        $vendorQuery = PurchaseItem::query()
            ->with(['purchase.vendor', 'product'])
            ->whereHas('purchase', function ($q): void {
                $q->where('status', 'active');
            })
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->orderBy('purchases.purchase_date', 'desc')
            ->orderBy('purchase_items.id', 'desc');

        if (! empty($filters['product_id'])) {
            $vendorQuery->where('purchase_items.product_id', $filters['product_id']);
        }

        $allPurchaseItems = $vendorQuery->select('purchase_items.*')->get();

        // Group by product_id and vendor_id, keep latest
        $grouped = $allPurchaseItems->groupBy(fn ($item) => $item->product_id.'_'.($item->purchase?->vendor_id ?? 0));

        $vendorComparison = collect();
        foreach ($grouped as $items) {
            $latest = $items->first();
            if (! $latest || ! $latest->purchase || ! $latest->purchase->vendor || ! $latest->product) {
                continue;
            }

            $pDate = $latest->purchase->purchase_date;
            $dateStr = $pDate ? (is_string($pDate) ? $pDate : $pDate->format('Y-m-d')) : '';

            $vendorComparison->push([
                'product_id' => (int) $latest->product_id,
                'product_name' => (string) $latest->product->name,
                'vendor_id' => (int) $latest->purchase->vendor_id,
                'vendor_name' => (string) $latest->purchase->vendor->name,
                'last_purchase_date' => $dateStr,
                'latest_unit_cost' => bcadd((string) $latest->unit_cost, '0.00', 2),
                'latest_landed_cost' => bcadd((string) $latest->landed_unit_cost, '0.00', 2),
            ]);
        }

        $vendorComparison = $vendorComparison->sortBy([
            ['product_name', 'asc'],
            ['vendor_name', 'asc'],
        ])->values();

        return [
            'price_changes' => $priceChanges,
            'vendor_comparison' => $vendorComparison,
        ];
    }
}
