<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $fillable = [
        'type',
        'current_number',
    ];

    protected function casts(): array
    {
        return [
            'current_number' => 'integer',
        ];
    }
}
