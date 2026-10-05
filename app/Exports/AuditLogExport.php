<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AuditLogExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Collection<int, mixed>  $rows
     */
    public function __construct(
        protected Collection $rows
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Timestamp',
            'Action / Description',
            'Subject Model',
            'Subject ID',
            'User / Causer',
            'Changes & Properties',
        ];
    }

    /**
     * @param  mixed  $row
     */
    public function map($row): array
    {
        return [
            $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : 'N/A',
            $row->description,
            class_basename($row->subject_type ?? ''),
            $row->subject_id ?? '',
            $row->causer ? $row->causer->name : 'System',
            json_encode($row->properties?->toArray() ?? [], JSON_UNESCAPED_SLASHES),
        ];
    }
}
