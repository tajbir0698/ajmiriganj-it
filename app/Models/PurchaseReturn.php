<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\ReturnSettlement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PurchaseReturn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'return_no',
        'purchase_id',
        'vendor_id',
        'return_date',
        'total_cost_removed',
        'credit_amount',
        'refund_received_amount',
        'loss_amount',
        'settlement',
        'refund_account_id',
        'refund_payment_method',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_cost_removed' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'refund_received_amount' => 'decimal:2',
            'loss_amount' => 'decimal:2',
            'settlement' => ReturnSettlement::class,
            'refund_payment_method' => PaymentMethod::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'return_no',
                'purchase_id',
                'vendor_id',
                'credit_amount',
                'refund_received_amount',
                'settlement',
            ])
            ->dontLogEmptyChanges();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(Transaction::class, 'reference');
    }
}
