<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountKind;
use App\Enums\TransactionType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'account_number',
        'opening_balance',
        'note',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountKind::class,
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getCurrentBalance(): string
    {
        $in = (string) $this->transactions()->where('type', TransactionType::IN->value)->sum('amount');
        $out = (string) $this->transactions()->where('type', TransactionType::OUT->value)->sum('amount');

        $balance = Money::add((string) $this->opening_balance, $in);

        return Money::sub($balance, $out);
    }
}
