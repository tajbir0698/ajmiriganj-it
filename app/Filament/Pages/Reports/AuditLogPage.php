<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Spatie\Activitylog\Models\Activity;

class AuditLogPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 17;

    protected static ?string $title = 'System Audit Log';

    protected string $view = 'filament.pages.reports.audit-log';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getLogsProperty()
    {
        return Activity::query()
            ->with('causer')
            ->latest('id')
            ->paginate(25);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('print')
                ->label('Print Audit Trail')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (): string => route('reports.print.audit-log'))
                ->openUrlInNewTab(),

            \Filament\Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $logs = Activity::query()->with('causer')->latest('id')->limit(1000)->get();
                    $filename = sprintf('audit_log_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AuditLogExport($logs), $filename);
                }),
        ];
    }
}
