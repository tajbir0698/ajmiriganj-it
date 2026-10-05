<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccountCategories\Pages;

use App\Filament\Resources\AccountCategories\AccountCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAccountCategory extends CreateRecord
{
    protected static string $resource = AccountCategoryResource::class;
}
