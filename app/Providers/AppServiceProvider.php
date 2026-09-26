<?php

namespace App\Providers;

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
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OutstandingBalance::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
}
