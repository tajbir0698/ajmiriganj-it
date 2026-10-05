<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountCategories\Pages;

use App\Filament\Resources\AccountCategories\AccountCategoryResource;
use App\Models\AccountCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountCategory extends EditRecord
{
    protected static string $resource = AccountCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (AccountCategory $record) => $record->isSystem() || $record->transactions()->exists()),
        ];
    }
}
