<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'category_id',
        'unit_id',
        'brand',
        'description',
        'image',
        'last_cost',
        'sale_price',
        'stock_qty',
        'alert_qty',
        'is_active',
        'needs_review',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_cost' => 'decimal:4',
            'sale_price' => 'decimal:2',
            'stock_qty' => 'decimal:3',
            'alert_qty' => 'decimal:3',
            'is_active' => 'boolean',
            'needs_review' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'sku',
                'barcode',
                'name',
                'category_id',
                'unit_id',
                'brand',
                'last_cost',
                'sale_price',
                'stock_qty',
                'alert_qty',
                'is_active',
                'needs_review',
                'created_by',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->orderBy('batch_date')->orderBy('id');
    }

    public function openBatches(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)->where('remaining_qty', '>', 0)->orderBy('batch_date')->orderBy('id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class)->orderByDesc('changed_at');
    }

    public function isLowStock(): bool
    {
        return (float) $this->alert_qty > 0 && (float) $this->stock_qty <= (float) $this->alert_qty && (float) $this->stock_qty > 0;
    }

    public function isOutOfStock(): bool
    {
        return (float) $this->stock_qty <= 0;
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('alert_qty', '>', 0)
            ->whereColumn('stock_qty', '<=', 'alert_qty')
            ->where('stock_qty', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock_qty', '<=', 0);
    }

    public function scopeLowOrOutOfStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('stock_qty', '<=', 0)
                ->orWhere(function (Builder $sub): void {
                    $sub->where('alert_qty', '>', 0)
                        ->whereColumn('stock_qty', '<=', 'alert_qty');
                });
        });
    }

    public function scopeSafeForManager(Builder $query): Builder
    {
        return $query->select([
            'id',
            'name',
            'sku',
            'barcode',
            'category_id',
            'unit_id',
            'sale_price',
            'stock_qty',
            'alert_qty',
            'is_active',
            'image',
            'created_at',
            'updated_at',
        ]);
    }

    public function getFormattedSalePriceAttribute(): string
    {
        return Money::format($this->sale_price);
    }

    public function getFormattedLastCostAttribute(): ?string
    {
        $user = auth()->user();
        if ($user && ! $user->hasRole(RoleName::SUPER_ADMIN->value)) {
            return null;
        }

        return Money::format($this->last_cost);
    }

    /**
     * Scope to protect server-side queries for non-super-admins
     */
    public function scopeForCurrentUser(Builder $query): Builder
    {
        $user = auth()->user();
        if ($user && ! $user->hasRole(RoleName::SUPER_ADMIN->value)) {
            // Exclude cost and administrative review fields from serialization
            $this->makeHidden(['last_cost', 'needs_review', 'created_by']);
        }

        return $query;
    }

    /**
     * Convert the model instance to an array, hiding cost and review tracking from non-admins.
     */
    public function toArray(): array
    {
        $attributes = parent::toArray();
        $user = auth()->user();

        if (! $user || ! $user->hasRole(RoleName::SUPER_ADMIN->value)) {
            unset($attributes['last_cost'], $attributes['needs_review'], $attributes['created_by']);
        }

        return $attributes;
    }
}
