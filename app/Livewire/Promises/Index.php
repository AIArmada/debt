<?php

namespace App\Livewire\Promises;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\RecordArchiveFilter;
use App\Domain\Enums\SubjectType;
use App\Domain\Presenters\HijriDatePresenter;
use App\Domain\Queries\BalanceBreakdown;
use App\Domain\Queries\DueDateQuery;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\ProfileTotals;
use App\Domain\Queries\RecordStatusQuery;
use App\Domain\Queries\RecordVisibilityQuery;
use App\Domain\StringNormalizer;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

final class Index extends Component
{
    public FinancialProfile $profile;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = RecordArchiveFilter::Active->value;

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('view', $profile);
    }

    public function render(ProfileTotals $profileTotals, OutstandingBalance $outstandingBalance, BalanceBreakdown $balanceBreakdown, RecordStatusQuery $recordStatus, DueDateQuery $dueDateQuery, RecordVisibilityQuery $recordVisibilityQuery, HijriDatePresenter $hijriDatePresenter): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $records = $this->recordsQuery($this->filter()->value)
            ->with([
                'partyLinks' => function (Relation $query): void {
                    $query->where('role', PartyRole::Counterparty->value)->where('is_primary', true)->with('party');
                },
                'obligations.moneySubject',
                'obligations.chargeTerms',
                'obligations.quantitySubject',
                'obligations.commitmentSubject',
            ])
            ->latest('created_at')
            ->latest('id')
            ->get();
        $obligations = $records->flatMap(static fn (Record $record) => $record->obligations);
        $breakdowns = $balanceBreakdown->forObligations($obligations);

        $overdue = $records->mapWithKeys(fn (Record $record): array => [
            $record->getKey() => $dueDateQuery->recordIsOverdue($record),
        ]);
        $records = $records->sortByDesc(fn (Record $record): int => ($overdue[$record->getKey()] ?? false) ? 1 : 0)->values();
        $filterCounts = collect(RecordArchiveFilter::cases())->mapWithKeys(fn (RecordArchiveFilter $filter): array => [
            $filter->value => $this->recordsQuery($filter->value)->count(),
        ]);

        $balances = $records->mapWithKeys(function (Record $record) use ($outstandingBalance, $breakdowns): array {
            $positions = [];
            foreach ($record->obligations->filter(static fn (Obligation $obligation): bool => $obligation->subject_type === SubjectType::Money) as $obligation) {
                foreach ($outstandingBalance->forObligation($obligation) as $currency => $balance) {
                    $breakdown = $breakdowns[(string) $obligation->getKey()][$currency] ?? [
                        'principal_in' => 0,
                        'charges_in' => 0,
                        'settled_total' => 0,
                    ];
                    $direction = $obligation->direction->forBalance($balance);
                    $positions[] = [
                        'currency' => $currency,
                        'amount' => abs($balance),
                        'direction' => $direction,
                        'label' => __('promises.directions.'.$direction->value),
                        'borrowed_minor' => (int) $breakdown['principal_in'],
                        'charges_minor' => (int) $breakdown['charges_in'],
                        'paid_minor' => (int) $breakdown['settled_total'],
                    ];
                }
            }

            return [$record->getKey() => $positions];
        });

        $statuses = $records->mapWithKeys(fn ($record): array => [
            $record->getKey() => $recordStatus->forRecord($record),
        ]);

        $dueSoon = $dueDateQuery->dueSoon($recordVisibilityQuery->forUser($this->profile->records()->getQuery(), $user))
            ->with(['obligations' => function (Relation $query): void {
                $query->where('status', ObligationStatus::Open->value)->with(['reminders', 'instalments']);
            }])
            ->orderBy('created_at')
            ->limit(5)
            ->get();

        return view('livewire.promises.index', [
            'balances' => $balances,
            'dueSoon' => $dueSoon,
            'statuses' => $statuses,
            'records' => $records,
            'overdue' => $overdue,
            'filterCounts' => $filterCounts,
            'totals' => $profileTotals->forProfile($this->profile, $user),
            'hijriDatePresenter' => $hijriDatePresenter,
        ])->layout('layouts.app', ['title' => __('promises.title')]);
    }

    private function filter(): RecordArchiveFilter
    {
        return RecordArchiveFilter::tryFrom($this->statusFilter) ?? RecordArchiveFilter::Active;
    }

    /** @return Builder<Record> */
    private function recordsQuery(string $filter): Builder
    {
        $search = StringNormalizer::trimmed($this->search);
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $records = app(RecordVisibilityQuery::class)->forUser($this->profile->records()->getQuery(), $user)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $recordQuery) use ($search): void {
                    $recordQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhereHas('partyLinks.party', function (Builder $partyQuery) use ($search): void {
                            $partyQuery->where('display_name', 'like', "%{$search}%");
                        });
                });
            });

        return match (RecordArchiveFilter::tryFrom($filter) ?? RecordArchiveFilter::Active) {
            RecordArchiveFilter::Active => $records->where('is_archived', false),
            RecordArchiveFilter::Archived => $records->where('is_archived', true),
            RecordArchiveFilter::Open => $records->where('is_archived', false)->whereHas('obligations', fn (Builder $query): Builder => $query->where('status', '!=', ObligationStatus::Settled->value)),
            RecordArchiveFilter::Paused => $records->where('is_archived', false)->whereHas('obligations', fn (Builder $query): Builder => $query->where('status', ObligationStatus::Paused->value)),
            RecordArchiveFilter::Disputed => $records->where('is_archived', false)->whereHas('obligations', fn (Builder $query): Builder => $query->where('status', ObligationStatus::Disputed->value)),
            RecordArchiveFilter::Settled => $records
                ->where('is_archived', false)
                ->whereHas('obligations')
                ->whereDoesntHave('obligations', fn (Builder $query): Builder => $query->where('status', '!=', ObligationStatus::Settled->value)),
            RecordArchiveFilter::DueSoon => app(DueDateQuery::class)->dueSoon($records),
            RecordArchiveFilter::Overdue => app(DueDateQuery::class)->overdue($records),
            RecordArchiveFilter::All => $records,
        };
    }
}
