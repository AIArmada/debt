<?php

namespace App\Features\PromiseTracker\EntryPoints\Filament;

use App\Features\PromiseTracker\EntryPoints\Filament\Auth\StaffLogin;
use App\Features\PromiseTracker\EntryPoints\Filament\Widgets\RecentSupportAuditEntries;
use App\Features\PromiseTracker\EntryPoints\Filament\Widgets\SupportOverview;
use App\Features\PromiseTracker\EntryPoints\Http\Middleware\EnsureFreshStaffMfa;
use App\Platform\Http\Middleware\SecurityHeaders;
use App\Platform\Http\Middleware\SetLocale;
use App\Platform\Http\Middleware\UseStandardAlpineForAdmin;
use Filament\Auth\MultiFactor\App\AppAuthentication;
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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->login(StaffLogin::class)
            ->authGuard('web')
            ->emailVerification()
            ->strictAuthorization()
            ->multiFactorAuthentication(AppAuthentication::make()->recoverable(), isRequired: true)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Features/PromiseTracker/EntryPoints/Filament/Resources'), for: 'App\Features\PromiseTracker\EntryPoints\Filament\Resources')
            ->discoverPages(in: app_path('Features/PromiseTracker/EntryPoints/Filament/Pages'), for: 'App\Features\PromiseTracker\EntryPoints\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                SupportOverview::class,
                RecentSupportAuditEntries::class,
            ])
            ->middleware([
                UseStandardAlpineForAdmin::class,
                SecurityHeaders::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureFreshStaffMfa::class,
            ], isPersistent: true);
    }
}
