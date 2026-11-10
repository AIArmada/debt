<?php

declare(strict_types=1);

namespace App\Features\PromiseTracker;

use App\Features\PromiseTracker\Commands\ExpireSupportAccessGrants;
use App\Features\PromiseTracker\Commands\GenerateDueCharges;
use App\Features\PromiseTracker\Commands\SendDueReminders;
use App\Features\PromiseTracker\Domain\Queries\OutstandingBalance;
use App\Features\PromiseTracker\EntryPoints\Auth\FortifyServiceProvider;
use App\Features\PromiseTracker\EntryPoints\Filament\AdminPanelProvider;
use App\Features\PromiseTracker\Listeners\CreatePersonalProfileForUser;
use App\Features\PromiseTracker\Models\Attachment;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\Party;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\SupportAccessGrant;
use App\Features\PromiseTracker\Models\SupportAuditEntry;
use App\Features\PromiseTracker\Models\SupportCase;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Persistence\SupportAuditLogger;
use App\Features\PromiseTracker\Policies\AttachmentPolicy;
use App\Features\PromiseTracker\Policies\FinancialProfilePolicy;
use App\Features\PromiseTracker\Policies\ObligationPolicy;
use App\Features\PromiseTracker\Policies\PartyPolicy;
use App\Features\PromiseTracker\Policies\RecordPolicy;
use App\Features\PromiseTracker\Policies\SupportAccessGrantPolicy;
use App\Features\PromiseTracker\Policies\SupportAuditEntryPolicy;
use App\Features\PromiseTracker\Policies\SupportCasePolicy;
use App\Features\PromiseTracker\Policies\UserPolicy;
use App\Platform\FeatureModule;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

final class FeatureServiceProvider extends FeatureModule
{
    public function register(): void
    {
        config(['auth.providers.users.model' => User::class]);

        $this->app->scoped(OutstandingBalance::class);
        $this->app->scoped(SupportAuditLogger::class);
        $this->app->register(FortifyServiceProvider::class);
        $this->app->register(AdminPanelProvider::class);

        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            /** @var class-string<Factory<Model>> $factory */
            $factory = 'Database\\Factories\\'.class_basename($modelName).'Factory';

            return $factory;
        });
    }

    public function boot(): void
    {
        parent::boot();

        $this->commands([
            ExpireSupportAccessGrants::class,
            GenerateDueCharges::class,
            SendDueReminders::class,
        ]);

        Livewire::addLocation(classNamespace: 'App\Features\PromiseTracker\EntryPoints\Livewire');

        Gate::policy(FinancialProfile::class, FinancialProfilePolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Obligation::class, ObligationPolicy::class);
        Gate::policy(Party::class, PartyPolicy::class);
        Gate::policy(Record::class, RecordPolicy::class);
        Gate::policy(SupportAccessGrant::class, SupportAccessGrantPolicy::class);
        Gate::policy(SupportAuditEntry::class, SupportAuditEntryPolicy::class);
        Gate::policy(SupportCase::class, SupportCasePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Event::listen('eloquent.created: '.User::class, CreatePersonalProfileForUser::class);

        View::composer('*::app.sidebar', function (ViewContract $view): void {
            $user = Auth::user();
            $view->with('unreadNotificationCount', $user instanceof User ? once(fn (): int => $user->unreadNotifications()->count()) : 0);
        });
    }
}
