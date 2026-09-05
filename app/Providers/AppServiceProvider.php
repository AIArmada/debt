<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Policies\DocumentPolicy;
use App\Policies\FinancialProfilePolicy;
use App\Policies\ObligationPolicy;
use App\Policies\RecordPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureOptionalCommerceViews();
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(FinancialProfile::class, FinancialProfilePolicy::class);
        Gate::policy(Obligation::class, ObligationPolicy::class);
        Gate::policy(Record::class, RecordPolicy::class);
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }

    private function configureOptionalCommerceViews(): void
    {
        if (class_exists(FilamentFilamentManager::class)) {
            return;
        }

        $this->app->booted(function (): void {
            $this->app['view']->getFinder()->replaceNamespace('commerce-support', [
                resource_path('views'),
            ]);
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
