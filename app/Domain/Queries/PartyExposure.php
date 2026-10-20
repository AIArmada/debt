<?php

namespace App\Domain\Queries;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\SubjectType;
use App\Domain\Money\LargestRemainderAllocator;
use App\Models\Obligation;
use App\Models\ObligationShare;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class PartyExposure
{
    public function __construct(
        private readonly MoneyBalanceQuery $moneyBalanceQuery,
        private readonly RecordVisibilityQuery $recordVisibilityQuery,
        private readonly LargestRemainderAllocator $shareAllocator,
    ) {}

    /** @return array{to_pay: array<string, int>, to_receive: array<string, int>, open_count: int, paused_count: int, disputed_count: int, settled_count: int} */
    public function forParty(Party $party, User $viewer): array
    {
        /** @var array{to_pay: array<string, int>, to_receive: array<string, int>} $totals */
        $totals = ['to_pay' => [], 'to_receive' => []];
        $visibleRecords = Record::query()
            ->where(function (Builder $query) use ($party): void {
                $query->whereHas('partyLinks', fn (Builder $links): Builder => $links
                    ->where('party_id', $party->getKey())
                    ->where('role', PartyRole::Counterparty->value)
                    ->where('is_primary', true))
                    ->orWhereHas('obligations.shares', fn (Builder $shares): Builder => $shares->where('party_id', $party->getKey()));
            });
        $visibleRecords = $this->recordVisibilityQuery->forUser($visibleRecords, $viewer)
            ->where('records.is_archived', false)
            ->select('records.id');
        $obligations = Obligation::query()
            ->whereIn('record_id', $visibleRecords)
            ->with(['shares', 'record.partyLinks'])
            ->get()
            ->values();

        $involvedObligations = $obligations->filter(fn (Obligation $obligation): bool => $this->isInvolved($party, $obligation))->values();

        foreach ($involvedObligations as $obligation) {
            if ($obligation->subject_type !== SubjectType::Money) {
                continue;
            }
            $shares = $obligation->shares;
            $shareBps = $shares->isEmpty()
                ? [['party_id' => (string) $party->getKey(), 'share_bps' => 10000]]
                : $shares->map(static fn (ObligationShare $share): array => [
                    'party_id' => (string) $share->party_id,
                    'share_bps' => (int) $share->share_bps,
                ])->values()->all();
            $shareBps = array_values($shareBps);
            $shareAmounts = $shares->isEmpty()
                ? [(string) $party->getKey() => 0]
                : [];
            foreach ($this->moneyBalanceQuery->forObligation($obligation) as $currency => $balance) {
                if ($balance === 0) {
                    continue;
                }
                if ($shares->isNotEmpty()) {
                    $shareAmounts = $this->shareAllocator->allocate(abs($balance), $shareBps);
                }
                $partyAmount = $shares->isEmpty() ? abs($balance) : ($shareAmounts[(string) $party->getKey()] ?? 0);
                if ($partyAmount === 0) {
                    continue;
                }
                $bucket = $obligation->direction->bucketForBalance($balance);
                $totals[$bucket][$currency] = ($totals[$bucket][$currency] ?? 0) + $partyAmount;
            }
        }

        return [
            'to_pay' => $totals['to_pay'],
            'to_receive' => $totals['to_receive'],
            'open_count' => $involvedObligations->filter(static fn (Obligation $obligation): bool => $obligation->status === ObligationStatus::Open)->count(),
            'paused_count' => $involvedObligations->filter(static fn (Obligation $obligation): bool => $obligation->status === ObligationStatus::Paused)->count(),
            'disputed_count' => $involvedObligations->filter(static fn (Obligation $obligation): bool => $obligation->status === ObligationStatus::Disputed)->count(),
            'settled_count' => $involvedObligations->filter(static fn (Obligation $obligation): bool => $obligation->status === ObligationStatus::Settled)->count(),
        ];
    }

    private function isInvolved(Party $party, Obligation $obligation): bool
    {
        if ($obligation->shares->isNotEmpty()) {
            return $obligation->shares->contains(static fn (ObligationShare $share): bool => $share->party_id === $party->getKey());
        }

        return $obligation->record->partyLinks->contains(static fn ($link): bool => $link->party_id === $party->getKey()
            && $link->role === PartyRole::Counterparty
            && $link->is_primary
        );
    }
}
