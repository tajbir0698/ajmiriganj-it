<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Ledger (Excel)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function (): \Symfony\Component\HttpFoundation\BinaryFileResponse {
                    $vendor = $this->getRecord();
                    $accountService = app(\App\Services\VendorAccountService::class);
                    $ledger = $accountService->getLedger($vendor);
                    $filename = sprintf('vendor_ledger_%s_%s.xlsx', $vendor->id, date('Ymd_His'));

                    return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\VendorLedgerExport($vendor, $ledger), $filename);
                }),
            EditAction::make(),
        ];
    }
}
