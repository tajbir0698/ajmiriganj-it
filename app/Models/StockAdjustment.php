<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class StockAdjustment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'adjustment_no',
        'product_id',
        'type',
        'qty',
        'unit_cost',
        'total_cost',
        'reason',
        'adjusted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdjustmentType::class,
            'qty' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'adjusted_at' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'adjustment_no',
                'product_id',
                'type',
                'qty',
                'unit_cost',
                'total_cost',
                'reason',
                'adjusted_at',
            ])
            ->dontLogEmptyChanges();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLosses(Builder $query): Builder
    {
        return $query->whereIn('type', [AdjustmentType::DECREASE, AdjustmentType::DAMAGE]);
    }
}
