<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use App\Models\Setting;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (Setting $record): bool => in_array($record->key, [
                    'site_title',
                    'favicon',
                    'shop_name',
                    'manager_sales_visibility',
                    'manager_can_add_products',
                    'manager_can_request_restock',
                ])),
        ];
    }
}
