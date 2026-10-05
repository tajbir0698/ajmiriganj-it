<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\DashboardWidgetRegistry;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return DashboardWidgetRegistry::getEnabledWidgetsForUser(auth()->user());
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.dashboard-pos-button'),
                ...(method_exists($this, 'getFiltersForm') ? [$this->getFiltersFormContentComponent()] : []),
                $this->getWidgetsContentComponent(),
                View::make('filament.pages.dashboard-empty-state')
                    ->visible(fn (): bool => empty($this->getWidgets())),
            ]);
    }
}
