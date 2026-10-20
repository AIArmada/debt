<?php

namespace App\Filament\Pages;

use App\Actions\Staff\RevokeProfileInvite;
use App\Domain\Enums\SupportAccessGrantStatus;
use App\Domain\Enums\SupportCaseStatus;
use App\Domain\Presenters\PaymentDestinationPresenter;
use App\Models\Attachment;
use App\Models\ImportBatch;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\PaymentDestination;
use App\Models\ProfileInvite;
use App\Models\ProfileMember;
use App\Models\QuantityReturn;
use App\Models\Record;
use App\Models\SupportAccessGrant;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportAuditLogger;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class SupportCaseWorkspace extends Page
{
    protected static ?string $slug = 'support-cases/{record}/workspace';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.support-case-workspace';

    #[Locked]
    public string $caseId;

    public function mount(string $record): void
    {
        $this->caseId = $record;
        $case = $this->supportCase();
        $actor = Filament::auth()->user();

        abort_unless($actor instanceof User, 403);

        $this->auditWorkspaceViewOnce($case, $case->activeGrant(), $actor);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $case = $this->supportCase();
        $supportCase = $case;
        $profile = $case->profile;
        $grant = $case->activeGrant();
        $actor = Filament::auth()->user();

        if (! $actor instanceof User) {
            abort(403);
        }

        if ($grant === null) {
            return [
                'supportCase' => $case,
                'profile' => $profile,
                'grant' => null,
                'canViewRecords' => false,
                'canViewMembers' => false,
                'records' => [],
                'attachments' => [],
                'parties' => [],
                'members' => [],
                'invites' => [],
                'imports' => [],
            ];
        }

        $canViewRecords = $actor->can('profileRecord.view');
        $canViewMembers = $actor->can('profileMember.view');
        $records = [];
        $attachments = [];
        $parties = [];
        $members = [];
        $invites = [];
        $imports = [];

        if ($canViewRecords) {
            $records = $profile->records()->with([
                'obligations.moneyMovements',
                'obligations.quantityReturns',
                'obligations.quantitySubject',
            ])->get()->map(fn (Record $record): array => [
                'title' => $record->title,
                'obligations' => $record->obligations->map(fn (Obligation $obligation): array => [
                    'title' => $obligation->title,
                    'status' => $obligation->status->value,
                    'quantity_subject' => $obligation->quantitySubject === null ? null : [
                        'description' => $obligation->quantitySubject->name,
                        'unit' => $obligation->quantitySubject->unit,
                        'total_quantity' => $obligation->quantitySubject->total,
                        'fractionable' => $obligation->quantitySubject->is_fractionable,
                    ],
                    'movements' => $obligation->moneyMovements->map(fn (MoneyMovement $movement): array => [
                        'entry' => $movement->entry->value,
                        'amount_minor' => $movement->amount_minor,
                        'currency' => $movement->currency,
                        'occurred_on' => $movement->occurred_on->toDateString(),
                    ])->all(),
                    'quantity_returns' => $obligation->quantityReturns->map(fn (QuantityReturn $quantityReturn): array => [
                        'quantity' => $quantityReturn->quantity,
                        'status' => $quantityReturn->status->value,
                        'occurred_on' => $quantityReturn->occurred_on->toDateString(),
                    ])->all(),
                ])->all(),
            ])->all();

            $parties = $profile->parties()->with(['contacts', 'paymentDestinations'])->get()->map(
                fn (Party $party): array => [
                    'name' => $party->display_name,
                    'contacts' => $party->contacts->map(fn (PartyContact $contact): array => ['label' => $contact->label, 'value' => $contact->value])->all(),
                    'destinations' => $party->paymentDestinations->map(fn (PaymentDestination $destination): string => app(PaymentDestinationPresenter::class)->masked($destination))->all(),
                ],
            )->all();

            $imports = $profile->importBatches()->latest()->get(['id', 'filename', 'row_count', 'created_at'])->map(
                fn (ImportBatch $batch): array => ['filename' => $batch->filename, 'row_count' => $batch->row_count, 'created_at' => $batch->created_at?->toDateTimeString()],
            )->all();

            $attachments = $profile->attachments()->latest('created_at')->get([
                'original_name',
                'category',
                'mime',
                'size_bytes',
                'created_at',
            ])->map(fn (Attachment $attachment): array => [
                'original_name' => $attachment->original_name,
                'category' => $attachment->category->value,
                'mime' => $attachment->mime,
                'size_bytes' => $attachment->size_bytes,
                'created_at' => $attachment->created_at->toDateTimeString(),
            ])->all();
        }

        if ($canViewMembers) {
            $members = $profile->members()->with('user')->get()->map(fn (ProfileMember $member): array => [
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value,
                'accepted_at' => $member->accepted_at?->toDateTimeString(),
            ])->all();

            $invites = $profile->invites()->latest()->get(['id', 'email', 'role', 'expires_at', 'accepted_at', 'revoked_at'])->map(
                fn (ProfileInvite $invite): array => [
                    'id' => (string) $invite->getKey(),
                    'email' => $invite->email,
                    'role' => $invite->role->value,
                    'expires_at' => $invite->expires_at->toDateTimeString(),
                    'accepted_at' => $invite->accepted_at?->toDateTimeString(),
                    'revoked_at' => $invite->revoked_at?->toDateTimeString(),
                    'usable' => $invite->isUsable(),
                ],
            )->all();
        }

        $grantIsStillActive = $case->grants()
            ->whereKey($grant->getKey())
            ->where('status', SupportAccessGrantStatus::Approved->value)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->whereHas('supportCase', fn (Builder $query): Builder => $query->where('status', SupportCaseStatus::Open->value))
            ->exists();

        abort_unless($grantIsStillActive, 403);

        return compact('supportCase', 'profile', 'grant', 'canViewRecords', 'canViewMembers', 'records', 'attachments', 'parties', 'members', 'invites', 'imports');
    }

    public function revokeInvite(string $inviteId): void
    {
        $case = $this->supportCase();
        $profile = $case->profile;
        $invite = $profile->invites()->whereKey($inviteId)->firstOrFail();
        $actor = Filament::auth()->user();
        abort_unless($actor instanceof User, 403);

        app(RevokeProfileInvite::class)->handle($actor, $invite, $case);
    }

    private function supportCase(): SupportCase
    {
        $case = SupportCase::query()->with('profile')->findOrFail($this->caseId);
        Gate::authorize('view', $case);

        return $case;
    }

    private function auditWorkspaceViewOnce(SupportCase $case, ?SupportAccessGrant $grant, User $actor): void
    {
        $key = implode(':', [
            'support-case-workspace-viewed',
            (string) $actor->getAuthIdentifier(),
            (string) $case->getKey(),
            (string) ($grant?->getKey() ?? 'none'),
        ]);

        if (session()->has($key)) {
            return;
        }

        if ($grant !== null && ($actor->can('profileRecord.view') || $actor->can('profileMember.view'))) {
            app(SupportAuditLogger::class)->record($actor, 'profile_data.viewed', $case, $case->profile, metadata: ['resource' => 'workspace']);
        } else {
            app(SupportAuditLogger::class)->record($actor, 'support_case.viewed', $case, subject: $case);
        }

        session()->put($key, true);
    }
}
