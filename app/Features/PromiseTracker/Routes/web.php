<?php

use App\Features\PromiseTracker\EntryPoints\Http\Controllers\AcceptInvitationController;
use App\Features\PromiseTracker\EntryPoints\Http\Controllers\AttachmentController;
use App\Features\PromiseTracker\EntryPoints\Http\Controllers\LocaleController;
use App\Features\PromiseTracker\EntryPoints\Http\Controllers\ProfileExportController;
use App\Features\PromiseTracker\EntryPoints\Http\Controllers\RecordShareController;
use App\Features\PromiseTracker\EntryPoints\Http\Controllers\StaffMfaChallengeController;
use App\Features\PromiseTracker\EntryPoints\Livewire\ExchangeRates\Index as ExchangeRatesIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Imports\Index as ImportsIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Members\Index as MembersIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Notifications\NotificationCenter;
use App\Features\PromiseTracker\EntryPoints\Livewire\Parties\Index as PeopleIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Parties\Show as PartyShow;
use App\Features\PromiseTracker\EntryPoints\Livewire\Plans\Index as PlansIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Plans\Show as PlansShow;
use App\Features\PromiseTracker\EntryPoints\Livewire\Profiles\Settings as ProfileSettings;
use App\Features\PromiseTracker\EntryPoints\Livewire\Profiles\Tokens as ProfileTokens;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Create as CreatePromise;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Index as PromisesIndex;
use App\Features\PromiseTracker\EntryPoints\Livewire\Promises\Show as ShowPromise;
use App\Features\PromiseTracker\Services\ProfileAccess;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/', function () {
        if (! auth()->check()) {
            return view('welcome');
        }

        $profile = app(ProfileAccess::class)->accessibleProfiles(auth()->user())->firstOrFail();

        return redirect()->route('promises.index', ['profile' => $profile]);
    })->name('home');

    Route::view('legal/privacy', 'legal.page', ['page' => 'privacy'])->name('legal.privacy');
    Route::view('legal/terms', 'legal.page', ['page' => 'terms'])->name('legal.terms');
    Route::view('legal/retention', 'legal.page', ['page' => 'retention'])->name('legal.retention');
    Route::view('legal/deletion', 'legal.page', ['page' => 'deletion'])->name('legal.deletion');
    Route::middleware(['auth:web', 'verified'])->group(function (): void {
        Route::get('staff/mfa/challenge', [StaffMfaChallengeController::class, 'show'])->name('staff.mfa.challenge');
        Route::post('staff/mfa/challenge', [StaffMfaChallengeController::class, 'verify'])->middleware('throttle:10,1')->name('staff.mfa.verify');
    });
    Route::get('locale/{locale}', LocaleController::class)->name('locale.switch');

    Route::get('s/{token}', [RecordShareController::class, 'show'])
        ->middleware('throttle:30,1')
        ->name('shares.show');
    Route::post('s/{token}/acknowledge', [RecordShareController::class, 'acknowledge'])
        ->middleware('throttle:10,1')
        ->name('shares.acknowledge');
    Route::post('s/{token}/dispute', [RecordShareController::class, 'dispute'])
        ->middleware('throttle:10,1')
        ->name('shares.dispute');

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::get('notifications', NotificationCenter::class)->name('notifications.index');
        Route::view('help/promise-support', 'help.promise-support')->name('help.promise-support');
        Route::get('p/{profile}/promises', PromisesIndex::class)->name('promises.index');
        Route::get('p/{profile}/promises/create', CreatePromise::class)->name('promises.create');
        Route::get('p/{profile}/promises/{record}', ShowPromise::class)
            ->scopeBindings()
            ->name('promises.show');
        Route::get('p/{profile}/people', PeopleIndex::class)->name('people.index');
        Route::get('p/{profile}/people/{party}', PartyShow::class)
            ->scopeBindings()
            ->name('people.show');
        Route::get('p/{profile}/settings/members', MembersIndex::class)->name('members.index');
        Route::get('p/{profile}/settings', ProfileSettings::class)->name('profile-settings.edit');
        Route::get('p/{profile}/settings/tokens', ProfileTokens::class)->name('profile-tokens.index');
        Route::get('p/{profile}/exports/promises.csv', [ProfileExportController::class, 'csv'])->name('profile.exports.csv');
        Route::get('p/{profile}/exports/promises.json', [ProfileExportController::class, 'json'])->name('profile.exports.json');
        Route::get('p/{profile}/exports/promises.zip', [ProfileExportController::class, 'zip'])->name('profile.exports.zip');
        Route::get('p/{profile}/exports/{format}/{export}', [ProfileExportController::class, 'download'])
            ->whereIn('format', ['csv', 'json'])
            ->middleware('signed')
            ->name('profile.exports.download');
        Route::get('p/{profile}/imports', ImportsIndex::class)->name('imports.index');
        Route::get('p/{profile}/exchange-rates', ExchangeRatesIndex::class)->name('exchange-rates.index');
        Route::get('p/{profile}/plans', PlansIndex::class)->name('plans.index');
        Route::get('p/{profile}/plans/{budgetPeriod}', PlansShow::class)->scopeBindings()->name('plans.show');
        Route::get('p/{profile}/attachments/{attachment}/download', [AttachmentController::class, 'download'])
            ->scopeBindings()
            ->middleware('signed')
            ->name('attachments.download');
    });

    Route::middleware(['auth', 'verified'])->get('invitations/{token}', AcceptInvitationController::class)
        ->name('invitations.accept');

    Route::middleware(['auth'])->group(function (): void {
        Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    });

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

        Route::livewire('settings/security', 'pages::settings.security')
            ->middleware(['password.confirm'])
            ->name('security.edit');
    });

    Route::get('.well-known/passkey-endpoints', function () {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    })->name('well-known.passkeys');
});
