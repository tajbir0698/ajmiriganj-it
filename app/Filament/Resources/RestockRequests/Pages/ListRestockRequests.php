<?php

declare(strict_types=1);

namespace App\Filament\Resources\RestockRequests\Pages;

use App\Filament\Resources\RestockRequests\RestockRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRestockRequests extends ListRecords
{
    protected static string $resource = RestockRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Request Restock'),
        ];
    }
}
