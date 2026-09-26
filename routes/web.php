<?php

use App\Http\Controllers\AcceptInvitationController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ProfileExportController;
use App\Livewire\ExchangeRates\Index as ExchangeRatesIndex;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Notifications\Index as NotificationsIndex;
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

Route::view('legal/privacy', 'legal.page', [
    'eyebrow' => 'Privacy',
    'title' => 'Privacy Policy',
    'updated' => '6 September 2026',
    'intro' => 'Promise Tracker stores the account, people, promises, movements, and activity history you choose to record.',
    'sections' => [
        ['title' => 'What we collect', 'body' => 'Account details and the promise information you enter.', 'items' => []],
        ['title' => 'How we use information', 'body' => 'We use it to show your promises, calculate transparent balances, and secure your account.', 'items' => []],
        ['title' => 'Your choices', 'body' => 'You can correct records, export your data, or delete your account from Settings.', 'items' => []],
    ],
])->name('legal.privacy');

Route::view('legal/terms', 'legal.page', [
    'eyebrow' => 'Agreement',
    'title' => 'Terms of Use',
    'updated' => '6 September 2026',
    'intro' => 'Promise Tracker is an organising tool for personal records. Review entries before relying on them.',
    'sections' => [
        ['title' => 'Your account', 'body' => 'Protect your credentials and do not access another person’s profile.', 'items' => []],
        ['title' => 'Your records', 'body' => 'You are responsible for confirming promises, movements, and settlement information.', 'items' => []],
        ['title' => 'No professional advice', 'body' => 'The application does not provide legal, financial, tax, or credit advice.', 'items' => []],
    ],
])->name('legal.terms');

Route::view('legal/retention', 'legal.page', [
    'eyebrow' => 'Data lifecycle',
    'title' => 'Retention Policy',
    'updated' => '6 September 2026',
    'intro' => 'Promise history is retained while your account needs it so each balance can be explained from its movements.',
    'sections' => [
        ['title' => 'Active accounts', 'body' => 'Active profiles, promises, movements, and activity history remain available to you.', 'items' => []],
        ['title' => 'Deletion', 'body' => 'Account deletion removes your profile and its records from the active system.', 'items' => []],
    ],
])->name('legal.retention');

Route::view('legal/deletion', 'legal.page', [
    'eyebrow' => 'Your control',
    'title' => 'Deletion Policy',
    'updated' => '6 September 2026',
    'intro' => 'Export your information before deleting your account if you may need the history later.',
    'sections' => [
        ['title' => 'What deletion does', 'body' => 'Account deletion removes the user, profile, people, promises, movements, and activity entries associated with the account.', 'items' => []],
        ['title' => 'How to request help', 'body' => 'Use the account deletion control in Settings if you cannot complete the process yourself.', 'items' => []],
    ],
])->name('legal.deletion');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('notifications', NotificationsIndex::class)->name('notifications.index');
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
    Route::get('p/{profile}/exports/{format}/{export}', [ProfileExportController::class, 'download'])
        ->whereIn('format', ['csv', 'json'])
        ->middleware('signed')
        ->name('profile.exports.download');
    Route::get('p/{profile}/imports', ImportsIndex::class)->name('imports.index');
    Route::get('p/{profile}/exchange-rates', ExchangeRatesIndex::class)->name('exchange-rates.index');
    Route::get('p/{profile}/plans', PlansIndex::class)->name('plans.index');
    Route::get('p/{profile}/plans/{period}', PlansShow::class)->scopeBindings()->name('plans.show');
    Route::get('p/{profile}/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->scopeBindings()
        ->middleware('signed')
        ->name('attachments.download');
});

Route::middleware(['auth', 'verified'])->get('invitations/{token}', AcceptInvitationController::class)
    ->name('invitations.accept');

require __DIR__.'/settings.php';
