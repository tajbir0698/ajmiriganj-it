<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use App\Services\AccountService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    public ?string $from_date = null;

    public ?string $to_date = null;

    protected function getHeaderActions(): array
    {
        /** @var Account $account */
        $account = $this->getRecord();

        return [
            EditAction::make(),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (AccountService $accountService): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    /** @var Account $record */
                    $record = $this->getRecord();
                    $ledger = $accountService->ledger($record);

                    $filename = sprintf('ledger_%s_%s.xlsx', str($record->name)->slug(), now()->format('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\AccountLedgerExport($record, $ledger), $filename);
                }),
        ];
    }
}
