<?php

namespace App\Providers\Filament;

use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Models\Business;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Actions\Action;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_BEFORE,
            fn() => view('filament.tables.toolbar-today-button'),
            scopes: [ListAppointments::class, ListPayments::class],
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            function (): HtmlString {
                try {
                    $palette = Color::hex('#334155');
                    $vars = implode('', array_map(
                        fn($shade, $value) => "--primary-{$shade}:{$value};",
                        array_keys($palette),
                        $palette
                    ));
                    return new HtmlString("<style>:root{{$vars}}</style>");
                } catch (\Throwable) {
                    return new HtmlString('');
                }
            }
        );
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->tenant(Business::class, slugAttribute: 'subdomain')
            ->path('admin')
            ->login();

        $baseDomain = config('app.base_domain');
        if ($baseDomain) {
            $panel = $panel->tenantDomain('{tenant:subdomain}.' . $baseDomain);
        }

        return $panel
            ->passwordReset()
            ->brandName(fn() => \App\Models\SalonProfile::current()->name ?? 'Booking App')
            ->brandLogo(asset('img/logo.png'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('img/logo.png'))
            ->colors([
                'primary' => Color::hex('#2563eb'),
            ])
            ->navigationGroups([
                NavigationGroup::make('Prenotazioni'),
                NavigationGroup::make('Salone'),
                NavigationGroup::make('Impostazioni'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->plugins([
                FilamentFullCalendarPlugin::make()
                    ->plugins(['resource', 'resourceTimeGrid'])
                    ->schedulerLicenseKey('CC-Attribution-NonCommercial-NoDerivatives'),
            ])
            ->userMenuItems([
                Action::make('portal')
                    ->label('Area Cliente')
                    ->url(fn(): string => route('portal.appointments.index'))
                    ->icon('heroicon-o-home'),
            ])
            // ->spa()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                \App\Http\Middleware\SubdomainMiddleware::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\CheckSubscription::class,
            ]);
    }
}
