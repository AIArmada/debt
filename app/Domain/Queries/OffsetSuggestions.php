<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\SubjectType;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OffsetSuggestions
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly RecordVisibilityQuery $recordVisibilityQuery,
    ) {}

    /**
     * @return list<array{key: string, payable_id: string, payable_title: string, receivable_id: string, receivable_title: string, currency: string, amount_minor: int}>
     */
    public function forParty(Party $party, User $viewer): array
    {
        $recordQuery = Record::query()
            ->where('profile_id', $party->profile_id)
            ->where('is_archived', false)
            ->whereHas('partyLinks', fn (Builder $query): Builder => $query
                ->where('party_id', $party->getKey())
                ->where('role', PartyRole::Counterparty->value)
                ->where('is_primary', true));
        $records = $this->recordVisibilityQuery->forUser($recordQuery, $viewer)
            ->with(['obligations.moneySubject'])
            ->oldest('created_at')
            ->get();
        $payable = [];
        $receivable = [];

        foreach ($records->flatMap->obligations as $obligation) {
            if ($obligation->subject_type !== SubjectType::Money || $obligation->status === ObligationStatus::Settled) {
                continue;
            }
            $currency = $obligation->moneySubject?->currency;
            if ($currency === null) {
                continue;
            }
            $balance = $this->outstandingBalance->forObligation($obligation)[$currency] ?? 0;
            if ($balance === 0) {
                continue;
            }
            $item = ['obligation' => $obligation, 'balance' => abs($balance), 'currency' => $currency];
            if ($obligation->direction === Direction::Payable) {
                $payable[] = $item;
            } else {
                $receivable[] = $item;
            }
        }

        $suggestions = [];
        foreach ($payable as $payableItem) {
            foreach ($receivable as $receivableItem) {
                if ($payableItem['currency'] !== $receivableItem['currency']) {
                    continue;
                }
                /** @var Obligation $payableObligation */
                $payableObligation = $payableItem['obligation'];
                /** @var Obligation $receivableObligation */
                $receivableObligation = $receivableItem['obligation'];
                $key = $payableObligation->getKey().':'.$receivableObligation->getKey();
                $suggestions[] = [
                    'key' => $key,
                    'payable_id' => (string) $payableObligation->getKey(),
                    'payable_title' => $payableObligation->title,
                    'receivable_id' => (string) $receivableObligation->getKey(),
                    'receivable_title' => $receivableObligation->title,
                    'currency' => $payableItem['currency'],
                    'amount_minor' => min($payableItem['balance'], $receivableItem['balance']),
                ];
            }
        }

        return $suggestions;
    }
}
