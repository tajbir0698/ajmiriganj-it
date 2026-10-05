<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountCategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'is_system',
        'is_active',
        'affects_profit',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountCategoryType::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'affects_profit' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'category_id');
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }
}
