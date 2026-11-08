<?php

declare(strict_types=1);

namespace App\Features\PromiseTracker;

use App\Features\PromiseTracker\Commands\ExpireSupportAccessGrants;
use App\Features\PromiseTracker\Commands\GenerateDueCharges;
use App\Features\PromiseTracker\Commands\SendDueReminders;
use App\Features\PromiseTracker\Domain\Queries\OutstandingBalance;
use App\Features\PromiseTracker\EntryPoints\Auth\FortifyServiceProvider;
use App\Features\PromiseTracker\EntryPoints\Filament\AdminPanelProvider;
use App\Features\PromiseTracker\EntryPoints\Livewire\ExchangeRates\Index as ExchangeRatesIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Imports\Index as ImportsIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Members\Index as MembersIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Notifications\NotificationCenter;
use App\Features\PromiseTracker\EntryPoints\Livewire\Parties\Index as PartiesIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Parties\Show as PartyShow;
use App\Features\PromiseTracker\EntryPoints\Livewire\Plans\Index as PlansIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Plans\Show as PlansShow;
use App\Features\PromiseTracker\EntryPoints\Livewire\Profiles\Settings as ProfileSettings;
use App\Features\PromiseTracker\EntryPoints\Livewire\Profiles\Tokens as ProfileTokens;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Create as CreatePromise;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Index as PromisesIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Show as ShowPromise;
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

        Livewire::component('exchange-rates.index', ExchangeRatesIndex::class);
        Livewire::component('imports.index', ImportsIndex::class);
        Livewire::component('members.index', MembersIndex::class);
        Livewire::component('notifications.notification-center', NotificationCenter::class);
        Livewire::component('parties.index', PartiesIndex::class);
        Livewire::component('parties.show', PartyShow::class);
        Livewire::component('plans.index', PlansIndex::class);
        Livewire::component('plans.show', PlansShow::class);
        Livewire::component('profiles.settings', ProfileSettings::class);
        Livewire::component('profiles.tokens', ProfileTokens::class);
        Livewire::component('promises.create', CreatePromise::class);
        Livewire::component('promises.index', PromisesIndex::class);
        Livewire::component('promises.show', ShowPromise::class);

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
