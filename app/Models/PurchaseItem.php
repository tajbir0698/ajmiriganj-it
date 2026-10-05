<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BatchSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'source',
        'batch_date',
        'qty',
        'unit_cost',
        'landed_unit_cost',
        'remaining_qty',
        'new_sale_price',
    ];

    protected function casts(): array
    {
        return [
            'source' => BatchSource::class,
            'batch_date' => 'date',
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'landed_unit_cost' => 'decimal:4',
            'remaining_qty' => 'decimal:3',
            'new_sale_price' => 'decimal:2',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class)->withDefault();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'purchase_item_id');
    }

    public function isUntouched(): bool
    {
        return bccomp((string) $this->remaining_qty, (string) $this->qty, 3) === 0;
    }

    public function isPartiallyOrFullyConsumed(): bool
    {
        return bccomp((string) $this->remaining_qty, (string) $this->qty, 3) < 0;
    }

    public function isPurchase(): bool
    {
        return $this->source === BatchSource::PURCHASE;
    }

    public function isOpening(): bool
    {
        return $this->source === BatchSource::OPENING;
    }

    public function isAdjustment(): bool
    {
        return $this->source === BatchSource::ADJUSTMENT;
    }

    public function getSourceDisplay(): string
    {
        if ($this->isPurchase() && $this->purchase?->invoice_no) {
            $vendorName = $this->purchase->vendor?->name ? " ({$this->purchase->vendor->name})" : '';

            return "{$this->purchase->invoice_no}{$vendorName}";
        }

        return $this->source?->label() ?? 'Standalone Batch';
    }
}
