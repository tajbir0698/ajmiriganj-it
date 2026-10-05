<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RestockRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RestockRequest extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'request_no',
        'requested_by',
        'status',
        'note',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'purchase_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RestockRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'request_no',
                'requested_by',
                'status',
                'note',
                'reviewed_by',
                'reviewed_at',
                'review_note',
                'purchase_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RestockRequestItem::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isPending(): bool
    {
        return $this->status === RestockRequestStatus::PENDING;
    }
}
