<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Product;
use DomainException;

class InsufficientStockException extends DomainException
{
    /**
     * @param  array<int, array{product: Product, requested: string, available: string}>  $shortages
     */
    public function __construct(
        public readonly ?Product $product = null,
        public readonly ?string $requestedQty = null,
        public readonly ?string $availableQty = null,
        public readonly array $shortages = [],
        string $message = '',
    ) {
        if ($message === '') {
            if (! empty($shortages)) {
                $items = array_map(
                    fn ($s) => "{$s['product']->name} (SKU: {$s['product']->sku}) - Requested: {$s['requested']}, Available: {$s['available']}",
                    $shortages
                );
                $message = 'Insufficient stock for items: '.implode('; ', $items);
            } elseif ($product) {
                $message = "Insufficient stock for {$product->name} (SKU: {$product->sku}). Requested: {$requestedQty}, Available: {$availableQty}.";
            } else {
                $message = 'Insufficient stock for requested operation.';
            }
        }

        parent::__construct($message);
    }
}
