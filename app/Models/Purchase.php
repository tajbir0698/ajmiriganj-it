<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Purchase extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'invoice_no',
        'vendor_invoice_no',
        'vendor_id',
        'purchase_date',
        'subtotal',
        'discount',
        'shipping_cost',
        'total',
        'paid_amount',
        'returned_amount',
        'due_amount',
        'payment_status',
        'status',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'returned_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'status' => PurchaseStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'invoice_no',
                'vendor_id',
                'purchase_date',
                'total',
                'paid_amount',
                'due_amount',
                'payment_status',
                'status',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === PurchaseStatus::ACTIVE;
    }

    public function isCancelled(): bool
    {
        return $this->status === PurchaseStatus::CANCELLED;
    }

    /**
     * Check if every batch created by this purchase is untouched (remaining_qty == qty).
     */
    public function isUntouched(): bool
    {
        foreach ($this->items as $item) {
            if (bccomp((string) $item->remaining_qty, (string) $item->qty, 3) !== 0) {
                return false;
            }
        }

        return true;
    }
}
