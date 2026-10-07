<?php

namespace App\Providers\Filament;

use App\Enums\RoleName;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\RestockRequests\RestockRequestResource;
use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName(function () {
                try {
                    return (string) (Setting::get('site_title') ?: Setting::get('shop_name') ?: Setting::get('company_name') ?: config('app.name', 'Ajmiriganj IT'));
                } catch (\Throwable) {
                    return config('app.name', 'Ajmiriganj IT');
                }
            })
            ->favicon(function () {
                try {
                    $favicon = Setting::get('favicon');
                    if (! empty($favicon)) {
                        if (str_starts_with($favicon, 'http://') || str_starts_with($favicon, 'https://') || str_starts_with($favicon, '/')) {
                            return $favicon;
                        }

                        return Storage::disk('public')->url($favicon);
                    }

                    return asset('favicon.ico');
                } catch (\Throwable) {
                    return asset('favicon.ico');
                }
            })
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->colors([
                'primary' => Color::hex('#a78bfa'),
            ])
            ->databaseNotifications()
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.hooks.sidebar-accordion'))
            ->navigationItems([
                NavigationItem::make('Request Restock')
                    ->url(fn (): string => RestockRequestResource::getUrl('create'))
                    ->icon('heroicon-o-plus-circle')
                    ->group('Procurement')
                    ->sort(2)
                    ->visible(fn (): bool => (bool) auth()->user()?->hasRole(RoleName::MANAGER->value) && (bool) Setting::get('manager_can_request_restock', false)),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
