<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_no',
        'date',
        'account_id',
        'category_id',
        'type',
        'source',
        'amount',
        'reference_type',
        'reference_id',
        'party_type',
        'party_id',
        'description',
        'transfer_group_id',
        'reversed_at',
        'reversed_by_id',
        'reversal_of_id',
        'reversal_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'source' => TransactionSource::class,
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'category_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversed_by_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversal_of_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }
}
