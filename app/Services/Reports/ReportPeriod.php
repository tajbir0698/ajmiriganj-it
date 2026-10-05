<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\Carbon;

class ReportPeriod
{
    public function __construct(
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public string $preset = 'this_month'
    ) {}

    public static function fromPreset(string $preset, ?string $customFrom = null, ?string $customTo = null): self
    {
        $tz = 'Asia/Dhaka';
        $now = now()->setTimezone($tz);

        return match ($preset) {
            'today' => new self(
                from: $now->copy()->startOfDay(),
                to: $now->copy()->endOfDay(),
                preset: 'today'
            ),
            'yesterday' => new self(
                from: $now->copy()->subDay()->startOfDay(),
                to: $now->copy()->subDay()->endOfDay(),
                preset: 'yesterday'
            ),
            'this_week' => new self(
                from: $now->copy()->startOfWeek(),
                to: $now->copy()->endOfWeek(),
                preset: 'this_week'
            ),
            'this_month' => new self(
                from: $now->copy()->startOfMonth(),
                to: $now->copy()->endOfMonth(),
                preset: 'this_month'
            ),
            'last_month' => new self(
                from: $now->copy()->subMonth()->startOfMonth(),
                to: $now->copy()->subMonth()->endOfMonth(),
                preset: 'last_month'
            ),
            'this_year' => new self(
                from: $now->copy()->startOfYear(),
                to: $now->copy()->endOfYear(),
                preset: 'this_year'
            ),
            'custom' => new self(
                from: $customFrom ? Carbon::parse($customFrom, $tz)->startOfDay() : null,
                to: $customTo ? Carbon::parse($customTo, $tz)->endOfDay() : null,
                preset: 'custom'
            ),
            'all' => new self(
                from: null,
                to: null,
                preset: 'all'
            ),
            default => new self(
                from: $now->copy()->startOfMonth(),
                to: $now->copy()->endOfMonth(),
                preset: 'this_month'
            ),
        };
    }

    public function fromDateString(): ?string
    {
        return $this->from?->toDateString();
    }

    public function toDateString(): ?string
    {
        return $this->to?->toDateString();
    }
}
