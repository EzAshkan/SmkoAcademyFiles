<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            // No separate admin login: admins sign in on the normal /login page.
            ->brandName(fn () => config('app.name').' · Admin')
            // Pages switch without a full reload, so background uploads keep running while you navigate.
            ->spa()
            ->renderHook(\Filament\View\PanelsRenderHook::HEAD_END, fn () => view('filament.uploader-script'))
            ->renderHook(\Filament\View\PanelsRenderHook::BODY_END, fn () => view('filament.uploader-panel'))
            ->homeUrl(fn () => route('library.home'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Widgets\SystemHealth::class,
                \App\Filament\Widgets\BackupStatus::class,
                \App\Filament\Widgets\OnlineNow::class,
                \App\Filament\Widgets\ActivityStats::class,
                \App\Filament\Widgets\TrafficOverview::class,
                \App\Filament\Widgets\BucketUsage::class,
                \App\Filament\Widgets\TelegramActivity::class,
                \App\Filament\Widgets\ActivityChart::class,
                \App\Filament\Widgets\TopFiles::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                \App\Http\Middleware\AdminMustChoosePassword::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
