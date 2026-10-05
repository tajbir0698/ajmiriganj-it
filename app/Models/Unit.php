<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Unit extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'short_name',
        'allow_fractional',
    ];

    protected function casts(): array
    {
        return [
            'allow_fractional' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'short_name', 'allow_fractional'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
