<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CashBook extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Accounts';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Daily Cash Book';

    protected string $view = 'filament.pages.cash-book';

    public ?string $date = null;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public function mount(): void
    {
        $this->date = request('date', now()->toDateString());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Cash Book')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.print(); return false;']),
        ];
    }
}
