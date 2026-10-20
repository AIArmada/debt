<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MovementStatus;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MoneyBalanceQuery
{
    private const string BALANCE_EXPRESSION = <<<'SQL'
        SUM(CASE
            WHEN money_movements.entry IN ('opening_balance', 'advance', 'charge', 'interest_charge', 'profit_charge', 'fee', 'penalty', 'adjustment_up')
                THEN money_movements.amount_minor
            WHEN money_movements.entry = 'adjustment_down'
                THEN -money_movements.amount_minor
            WHEN money_movements.entry = 'payment' AND obligations.direction = 'payable'
                THEN -money_movements.amount_minor
            WHEN money_movements.entry = 'payment' AND obligations.direction = 'receivable'
                THEN money_movements.amount_minor
            WHEN money_movements.entry = 'collection' AND obligations.direction = 'receivable'
                THEN -money_movements.amount_minor
            WHEN money_movements.entry = 'collection' AND obligations.direction = 'payable'
                THEN money_movements.amount_minor
            WHEN money_movements.entry IN ('forgiveness', 'write_off', 'offset')
                THEN COALESCE(NULLIF(money_movements.calculation->>'signed_effect', '')::integer, -1) * money_movements.amount_minor
            ELSE 0
        END)
        SQL;

    public function __construct(private readonly RecordVisibilityQuery $recordVisibilityQuery) {}

    /** @return array<string, int> */
    public function forObligation(Obligation $obligation): array
    {
        return DB::table('money_movements')
            ->join('obligations', 'obligations.id', '=', 'money_movements.obligation_id')
            ->where('money_movements.obligation_id', $obligation->getKey())
            ->where('money_movements.status', MovementStatus::Confirmed->value)
            ->selectRaw('money_movements.currency, '.self::BALANCE_EXPRESSION.' AS balance')
            ->groupBy('money_movements.currency')
            ->pluck('balance', 'currency')
            ->map(fn (mixed $balance): int => (int) $balance)
            ->all();
    }

    /** @return Collection<int, \stdClass> */
    public function forProfile(FinancialProfile $profile, User $viewer): Collection
    {
        $visibleRecordIds = $this->recordVisibilityQuery
            ->forUser($profile->records()->getQuery(), $viewer)
            ->select('records.id');

        return DB::table('money_movements')
            ->join('obligations', 'obligations.id', '=', 'money_movements.obligation_id')
            ->join('records', 'records.id', '=', 'obligations.record_id')
            ->where('records.profile_id', $profile->getKey())
            ->where('records.is_archived', false)
            ->whereIn('records.id', $visibleRecordIds)
            ->where('money_movements.status', MovementStatus::Confirmed->value)
            ->selectRaw(
                'obligations.id, obligations.direction, money_movements.currency, '.self::BALANCE_EXPRESSION.' AS balance',
            )
            ->groupBy('obligations.id', 'obligations.direction', 'money_movements.currency')
            ->get();
    }
}
