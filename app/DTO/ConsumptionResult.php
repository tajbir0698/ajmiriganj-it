<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ConsumptionResult
{
    /**
     * @param  array<int, array{purchase_item_id: int, qty: string, unit_cost: string}>  $allocations
     * @param  string  $totalCost  Formatted as scale 2 decimal
     * @param  string  $averageUnitCost  Formatted as scale 4 decimal
     * @param  string  $totalQty  Formatted as scale 3 decimal
     */
    public function __construct(
        public array $allocations,
        public string $totalCost,
        public string $averageUnitCost,
        public string $totalQty,
    ) {}
}
