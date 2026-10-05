<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPaymentAllocation extends Model
{
    protected $fillable = [
        'vendor_payment_id',
        'purchase_id',
        'amount',
        'is_advance_application',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_advance_application' => 'boolean',
        ];
    }

    public function vendorPayment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
