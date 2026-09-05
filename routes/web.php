<?php

use App\Http\Controllers\AcceptProfileInvitationController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExportUserDataController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PushSubscriptionController;
use App\Livewire\Dashboard;
use App\Livewire\Imports\Index;
use App\Livewire\Integrations\Index as IntegrationsIndex;
use App\Livewire\Notifications\Settings;
use App\Livewire\Obligations\Edit as EditObligation;
use App\Livewire\Parties\Index as PartiesIndex;
use App\Livewire\Plans\Index as PlansIndex;
use App\Livewire\Profiles\Edit as EditFinancialProfile;
use App\Livewire\Profiles\Index as FinancialProfilesIndex;
use App\Livewire\Records\Create as CreateRecord;
use App\Livewire\Records\Edit as EditRecord;
use App\Livewire\Records\Index as RecordsIndex;
use App\Livewire\Records\Show as ShowRecord;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
})->name('home');

Route::view('legal/privacy', 'legal.page', [
    'eyebrow' => 'Privacy', 'title' => 'Privacy Policy', 'updated' => '3 September 2026',
    'intro' => 'Debt Management is designed for sensitive financial and relationship information. This policy explains what the service stores, why it stores it, and the choices you have.',
    'sections' => [
        ['title' => 'What we collect', 'body' => 'We collect account details, financial profiles, obligation records, uploaded evidence, planning inputs, messages you draft, and activity needed to secure the service. Optional imports and integrations are only processed when you choose to use them.', 'items' => ['Financial records are yours to enter and may be incomplete or estimated.', 'Payment-provider credentials, when configured, are kept server-side and encrypted.', 'Notification content is generic by default so locked screens do not reveal names or balances.']],
        ['title' => 'How we use information', 'body' => 'We use information to display your records, calculate transparent estimates, generate plans you request, deliver reminders you enable, protect the service, and provide exports or support you request. We do not sell debt records or use them to create a credit score.', 'items' => []],
        ['title' => 'Sharing and access', 'body' => 'Records are private to your account and profile by default. If you invite a collaborator, access is limited by role. A party recorded in a case does not need an account. Provider integrations receive only the information needed for the action you explicitly authorise.', 'items' => []],
        ['title' => 'Security', 'body' => 'The application uses authenticated sessions, role checks, encrypted sensitive values, private document storage, append-only activity history, rate limits, and signed webhook checks. No online service can promise perfect security; keep your password and recovery methods private.', 'items' => []],
        ['title' => 'Your choices', 'body' => 'You can correct records, pause schedules, change notification preferences, export your data, revoke collaborators, disconnect integrations, and request account deletion. Contact the service owner if you need help exercising a choice.', 'items' => []],
        ['title' => 'Important limits', 'body' => 'Debt Management is an organising and planning tool. It is not financial, legal, credit, tax, or religious advice, and estimates do not replace an agreement, statement, or professional advice.', 'items' => []],
    ],
])->name('legal.privacy');
Route::view('legal/terms', 'legal.page', [
    'eyebrow' => 'Agreement', 'title' => 'Terms of Use', 'updated' => '3 September 2026',
    'intro' => 'These terms set the boundaries for using Debt Management responsibly. By using the service, you agree to keep your account secure and review important actions before relying on them.',
    'sections' => [
        ['title' => 'Your account', 'body' => 'You are responsible for accurate account information, protecting your credentials, and activity performed through your account. You must not use the service to impersonate another person or access a profile without permission.', 'items' => []],
        ['title' => 'Your records and decisions', 'body' => 'You keep ownership of information you enter. You are responsible for confirming balances, terms, calculations, parties, and payment instructions. Generated plans, messages, conversions, and estimates require your review.', 'items' => []],
        ['title' => 'Payments and integrations', 'body' => 'The service does not hold funds. Manual records are not proof of settlement. Any provider action requires an explicit authorisation, is subject to provider availability and limits, and may require reconciliation. Never approve a payment you have not independently checked.', 'items' => []],
        ['title' => 'Acceptable use', 'body' => 'Do not upload malware, unlawful material, someone else’s confidential information without permission, or credentials intended for another person. Do not attempt to bypass role restrictions, rate limits, or webhook verification.', 'items' => []],
        ['title' => 'Availability and changes', 'body' => 'Features, providers, currencies, and notification channels may vary by country and configuration. We may change or retire features to improve safety. We will not describe a feature as active when it is only a future integration.', 'items' => []],
        ['title' => 'No professional advice', 'body' => 'Nothing in the service creates a lender, debtor, fiduciary, legal, tax, financial, credit, or religious advisory relationship. Seek qualified advice where the consequences matter.', 'items' => []],
    ],
])->name('legal.terms');
Route::view('legal/retention', 'legal.page', [
    'eyebrow' => 'Data lifecycle', 'title' => 'Retention Policy', 'updated' => '3 September 2026',
    'intro' => 'Retention is purposeful: keep enough history to explain a record while avoiding indefinite storage of information you no longer need.',
    'sections' => [
        ['title' => 'Active accounts', 'body' => 'We retain active profiles, records, evidence, plans, messages, imports, and audit history while your account needs them. Archived records remain available to you until you delete them or delete your account.', 'items' => []],
        ['title' => 'Operational history', 'body' => 'Append-only activity entries and payment execution attempts may be retained for security, reconciliation, dispute handling, and legal obligations. They are not used to expose a profile to people without access.', 'items' => []],
        ['title' => 'Notifications and imports', 'body' => 'Notification records are retained in your account until removed under the deletion process. Imported statement rows remain in the review history until you delete the account; avoid uploading statements you are not authorised to use.', 'items' => []],
        ['title' => 'Backups and legal holds', 'body' => 'Deletion from the active system may take time to propagate to encrypted backups or legally required records. Backups are access-controlled and expire through the provider’s backup cycle.', 'items' => []],
        ['title' => 'Review', 'body' => 'This policy is reviewed when storage, provider, or legal requirements change. The date at the top shows the latest review.', 'items' => []],
    ],
])->name('legal.retention');
Route::view('legal/deletion', 'legal.page', [
    'eyebrow' => 'Your control', 'title' => 'Deletion Policy', 'updated' => '3 September 2026',
    'intro' => 'You can leave with a copy of your information and request deletion of your account. Deletion is permanent for the active system, so export first if you may need the history later.',
    'sections' => [
        ['title' => 'Before deleting', 'body' => 'Use Export my data from Profiles to download a JSON copy. Pause schedules, revoke invitations, disconnect integrations, and confirm you no longer need the records or evidence.', 'items' => []],
        ['title' => 'What deletion does', 'body' => 'Account deletion removes the user account and the profile records owned by it through the application’s deletion rules, including obligations, documents, plans, imports, schedules, notifications, and profile memberships. Private files are removed from the configured storage disk when their records are removed.', 'items' => []],
        ['title' => 'What may remain temporarily', 'body' => 'Encrypted backups, security logs, webhook records, or records we must keep to meet legal obligations may remain for the period required to expire or resolve them. They remain access-controlled and are not used for ordinary product display.', 'items' => []],
        ['title' => 'How to request help', 'body' => 'Use the account deletion control in Settings, or contact the service owner if you cannot sign in. We may need to verify account ownership before acting on a request.', 'items' => []],
    ],
])->name('legal.deletion');
Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)->middleware('throttle:api')->name('webhooks.payments');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('records', RecordsIndex::class)->name('records.index');
    Route::get('records/create', CreateRecord::class)->name('records.create');
    Route::get('records/{record}/edit', EditRecord::class)->name('records.edit');
    Route::get('records/{record}/obligations/{obligation}/edit', EditObligation::class)->name('records.obligations.edit');
    Route::get('records/{record}', ShowRecord::class)->name('records.show');
    Route::get('parties', PartiesIndex::class)->name('parties.index');
    Route::get('profiles', FinancialProfilesIndex::class)->name('financial-profiles.index');
    Route::get('profiles/{profile}/edit', EditFinancialProfile::class)->name('financial-profiles.edit');
    Route::get('plans', PlansIndex::class)->name('plans.index');
    Route::get('imports', Index::class)->name('imports.index');
    Route::get('integrations', IntegrationsIndex::class)->name('integrations.index');
    Route::get('settings/notifications', Settings::class)->name('notifications.settings');
    Route::post('settings/push-subscription', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('settings/push-subscription/{subscription}', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::get('documents/{document}', DocumentDownloadController::class)->name('documents.download');
    Route::get('invitations/{token}', AcceptProfileInvitationController::class)->name('invitations.accept');
    Route::get('export', ExportUserDataController::class)->name('data.export');
});

require __DIR__.'/settings.php';
