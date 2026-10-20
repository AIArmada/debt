<?php

namespace App\Providers;

use AIArmada\Authz\Guard\SessionGuard as AuthzSessionGuard;
use App\Domain\Queries\OutstandingBalance;
use App\Models\Attachment;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\FinancialProfilePolicy;
use App\Policies\ObligationPolicy;
use App\Policies\PartyPolicy;
use App\Policies\RecordPolicy;
use App\Services\SupportAuditLogger;
use Carbon\CarbonImmutable;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use PragmaRX\Google2FAQRCode\Google2FA;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OutstandingBalance::class);
        $this->app->scoped(SupportAuditLogger::class);
        $this->app->singleton(AppAuthentication::class, static fn (Application $app): AppAuthentication => (new AppAuthentication($app->make(Google2FA::class)))
            ->codeWindow(1));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthzSessionGuard();
        Migrator::withoutMigrations([
            base_path('vendor/aiarmada/authz/database/migrations/2000_01_01_000002_create_authz_permission_tables.php'),
        ]);
        $this->configureDefaults();
        Gate::policy(FinancialProfile::class, FinancialProfilePolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Obligation::class, ObligationPolicy::class);
        Gate::policy(Party::class, PartyPolicy::class);
        Gate::policy(Record::class, RecordPolicy::class);
        View::composer('*::app.sidebar', function (ViewContract $view): void {
            $user = Auth::user();
            $view->with('unreadNotificationCount', $user instanceof User ? once(fn (): int => $user->unreadNotifications()->count()) : 0);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $rule = Password::min(8);

            return app()->isProduction()
                ? $rule
                    ->min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : $rule;
        });
    }

    private function configureAuthzSessionGuard(): void
    {
        Auth::extend('session', function (Application $app, string $name, array $config): AuthzSessionGuard {
            $auth = $app['auth'];
            $guard = new AuthzSessionGuard(
                $name,
                $auth->createUserProvider($config['provider'] ?? null),
                $app['session.store'],
                $app['request'] ?? null,
                rehashOnLogin: (bool) $app['config']->get('hashing.rehash_on_login', true),
                timeboxDuration: (int) $app['config']->get('auth.timebox_duration', 200000),
                hashKey: (string) $app['config']->get('app.key'),
            );

            $guard->setCookieJar($app['cookie']);
            $guard->setDispatcher($app['events']);
            $guard->setRequest($app->refresh('request', $guard, 'setRequest'));

            if (isset($config['remember'])) {
                $guard->setRememberDuration($config['remember']);
            }

            return $guard;
        });

        Auth::forgetGuards();
    }
}
