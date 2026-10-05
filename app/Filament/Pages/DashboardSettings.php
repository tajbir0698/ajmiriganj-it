<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DashboardWidgetRegistry;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class DashboardSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 11;

    protected static ?string $title = 'Dashboard Settings';

    protected static ?string $slug = 'dashboard-settings';

    protected string $view = 'filament.pages.dashboard-settings';

    public array $super_admin = [];

    public array $manager = [];

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationItems(): array
    {
        return array_map(
            fn (\Filament\Navigation\NavigationItem $item) => $item->visible(fn (): bool => static::canAccess()),
            parent::getNavigationItems()
        );
    }

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $settings = DashboardWidgetRegistry::getSettings();
        $this->super_admin = $settings['super_admin'];
        $this->manager = $settings['manager'];
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        DashboardWidgetRegistry::saveSettings([
            'super_admin' => $this->super_admin,
            'manager' => $this->manager,
        ], auth()->user());

        Notification::make()
            ->title('Dashboard Settings Saved')
            ->body('Dashboard widget visibility configurations have been updated successfully.')
            ->success()
            ->send();
    }

    public function resetDefaults(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        DashboardWidgetRegistry::resetToDefaults(auth()->user());

        $defaults = DashboardWidgetRegistry::getDefaults();
        $this->super_admin = $defaults['super_admin'];
        $this->manager = $defaults['manager'];

        Notification::make()
            ->title('Reset to Defaults')
            ->body('Dashboard widget visibility configurations have been reset to factory defaults.')
            ->info()
            ->send();
    }

    /**
     * @return array<string, array>
     */
    public function getRegisteredWidgets(): array
    {
        return DashboardWidgetRegistry::getRegisteredWidgets();
    }
}
