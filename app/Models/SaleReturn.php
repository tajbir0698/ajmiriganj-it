<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SaleReturn extends Model
{
    use LogsActivity;

    protected $fillable = [
        'return_no',
        'sale_id',
        'customer_id',
        'return_date',
        'refund_amount',
        'due_reduction',
        'cash_refund',
        'cost_restored',
        'profit_reversed',
        'refund_account_id',
        'refund_payment_method',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'refund_amount' => 'decimal:2',
            'due_reduction' => 'decimal:2',
            'cash_refund' => 'decimal:2',
            'cost_restored' => 'decimal:2',
            'profit_reversed' => 'decimal:2',
            'refund_payment_method' => PaymentMethod::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'return_no',
                'sale_id',
                'customer_id',
                'refund_amount',
                'due_reduction',
                'cash_refund',
                'cost_restored',
                'profit_reversed',
            ])
            ->dontLogEmptyChanges();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
        return $this->hasMany(SaleReturnItem::class);
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
