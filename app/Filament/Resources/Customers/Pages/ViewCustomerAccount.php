<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewCustomerAccount extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Ledger (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $customer = $this->getRecord();
                    $accountService = app(\App\Services\CustomerAccountService::class);
                    $ledger = $accountService->getLedger($customer);
                    $filename = sprintf('customer_ledger_%s_%s.xlsx', $customer->id, date('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\CustomerLedgerExport($customer, $ledger), $filename);
                }),
            EditAction::make(),
        ];
    }
}
