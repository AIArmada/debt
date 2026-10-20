<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileExportController;
use App\Http\Controllers\RecordShareController;
use App\Http\Controllers\StaffMfaChallengeController;
use App\Livewire\ExchangeRates\Index as ExchangeRatesIndex;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Parties\Index as PeopleIndex;
use App\Livewire\Parties\Show as PartyShow;
use App\Livewire\Plans\Index as PlansIndex;
use App\Livewire\Plans\Show as PlansShow;
use App\Livewire\Profiles\Settings as ProfileSettings;
use App\Livewire\Profiles\Tokens as ProfileTokens;
use App\Livewire\Promises\Create as CreatePromise;
use App\Livewire\Promises\Index as PromisesIndex;
use App\Livewire\Promises\Show as ShowPromise;
use App\Services\ProfileAccess;
use Illuminate\Support\Facades\Route;

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
    Route::view('help/debt-support', 'help.debt-support')->name('help.debt-support');
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

require __DIR__.'/settings.php';
