<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\VendorAccountService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Vendor extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_person',
        'phone',
        'alt_phone',
        'email',
        'address',
        'opening_balance',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'contact_person',
                'phone',
                'email',
                'opening_balance',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function getCurrentDueAttribute(): string
    {
        return app(VendorAccountService::class)->getCurrentDue($this);
    }

    public function getTotalPurchasedAttribute(): string
    {
        return app(VendorAccountService::class)->getTotalPurchased($this);
    }

    public function getTotalPaidAttribute(): string
    {
        return app(VendorAccountService::class)->getTotalPaid($this);
    }

    public function getLastPurchaseDateAttribute(): ?string
    {
        return app(VendorAccountService::class)->getLastPurchaseDate($this);
    }

    public function getLastPaymentDateAttribute(): ?string
    {
        return app(VendorAccountService::class)->getLastPaymentDate($this);
    }
}
