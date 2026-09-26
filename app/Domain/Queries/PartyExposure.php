<?php

namespace App\Domain\Queries;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Models\Obligation;
use App\Models\Party;
use App\Models\Record;
use Illuminate\Database\Eloquent\Collection;

final class PartyExposure
{
    public function __construct(private readonly MoneyBalanceQuery $moneyBalanceQuery) {}

    /** @return array{to_pay: array<string, int>, to_receive: array<string, int>, open_count: int, settled_count: int} */
    public function forParty(Party $party): array
    {
        /** @var array{to_pay: array<string, int>, to_receive: array<string, int>} $totals */
        $totals = ['to_pay' => [], 'to_receive' => []];
        $obligations = $party->records()
            ->where('records.is_archived', false)
            ->with('obligations')
            ->get()
            ->flatMap(static fn (Record $record): Collection => $record->obligations)
            ->values();

        $moneyObligations = $obligations->filter(static fn (Obligation $obligation): bool => $obligation->subject_type === SubjectType::Money);

        foreach ($moneyObligations as $obligation) {
            foreach ($this->moneyBalanceQuery->forObligation($obligation) as $currency => $balance) {
                if ($balance === 0) {
                    continue;
                }
                $bucket = $obligation->direction->bucketForBalance($balance);
                $totals[$bucket][$currency] = ($totals[$bucket][$currency] ?? 0) + abs($balance);
            }
        }

        return [
            'to_pay' => $totals['to_pay'],
            'to_receive' => $totals['to_receive'],
            'open_count' => $obligations->filter(static fn (Obligation $obligation): bool => $obligation->status === ObligationStatus::Open)->count(),
            'settled_count' => $obligations->filter(static fn (Obligation $obligation): bool => $obligation->status === ObligationStatus::Settled)->count(),
        ];
    }
}
