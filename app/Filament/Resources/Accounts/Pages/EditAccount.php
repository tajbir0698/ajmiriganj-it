<?php

declare(strict_types=1);

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\Account;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn (Account $record) => $record->transactions()->exists()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Account $record */
        $record = $this->getRecord();
        $oldOpening = (string) $record->opening_balance;
        $newOpening = (string) ($data['opening_balance'] ?? $oldOpening);

        if (bccomp($oldOpening, $newOpening, 2) !== 0 && $record->transactions()->exists()) {
            activity('accounts')
                ->performedOn($record)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_opening_balance' => $oldOpening,
                    'new_opening_balance' => $newOpening,
                ])
                ->log("Updated opening balance for account {$record->name}");
        }

        unset($data['confirm_opening_balance_change']);

        return $data;
    }
}
