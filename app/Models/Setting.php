<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Setting extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['key', 'value'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        static::saved(function (Setting $setting) {
            cache()->forget("setting.{$setting->key}");
        });
        static::deleted(function (Setting $setting) {
            cache()->forget("setting.{$setting->key}");
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $cached = cache()->remember("setting.{$key}", 3600, function () use ($key) {
            $setting = static::where('key', $key)->first();
            if (! $setting) {
                return null;
            }

            return [
                'value' => $setting->value,
                'type' => $setting->type,
            ];
        });

        if (! $cached) {
            return $default;
        }

        return match ($cached['type']) {
            'boolean', 'bool' => filter_var($cached['value'], FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $cached['value'],
            'float', 'double', 'decimal' => (float) $cached['value'],
            'json', 'array' => json_decode((string) $cached['value'], true),
            default => $cached['value'],
        };
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): self
    {
        $stringValue = is_bool($value)
            ? ($value ? '1' : '0')
            : (is_array($value) ? json_encode($value) : (string) $value);

        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stringValue,
                'type' => $type,
                'description' => $description,
            ]
        );

        cache()->forget("setting.{$key}");

        return $setting;
    }
}

