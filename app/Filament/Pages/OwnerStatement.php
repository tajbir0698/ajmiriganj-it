<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Models\User;
use App\Services\BusinessFinanceService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerStatement extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Owner Statement';

    protected string $view = 'filament.pages.owner-statement';

    public ?string $from = null;

    public ?string $to = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->from = request('from');
        $this->to = request('to');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Statement')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.print(); return false;']),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (BusinessFinanceService $financeService): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $fromDate = $this->from ? Carbon::parse($this->from) : null;
                    $toDate = $this->to ? Carbon::parse($this->to) : null;

                    $statementData = [
                        'investments' => $financeService->ownerInvestment($fromDate, $toDate),
                        'drawings' => $financeService->ownerDrawings($fromDate, $toDate),
                        'netProfit' => $financeService->netProfit($fromDate, $toDate),
                        'withdrawals' => $financeService->profitWithdrawals($fromDate, $toDate),
                        'retainedProfit' => $financeService->retainedProfit(),
                        'ownerCapital' => $financeService->ownerCapital(),
                    ];

                    $filename = sprintf('owner_statement_%s.xlsx', now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\OwnerStatementExport($statementData), $filename);
                }),
        ];
    }
}
