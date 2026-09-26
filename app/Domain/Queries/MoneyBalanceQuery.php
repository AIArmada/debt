<?php

namespace App\Domain\Queries;

use App\Domain\Enums\Direction;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MoneyBalanceQuery
{
    /** @return array<string, int> */
    public function forObligation(Obligation $obligation): array
    {
        return DB::table('money_movements')
            ->join('obligations', 'obligations.id', '=', 'money_movements.obligation_id')
            ->where('money_movements.obligation_id', $obligation->getKey())
            ->where('money_movements.status', MovementStatus::Confirmed->value)
            ->selectRaw('money_movements.currency, '.$this->balanceExpression().' AS balance', $this->balanceBindings())
            ->groupBy('money_movements.currency')
            ->pluck('balance', 'currency')
            ->map(fn (mixed $balance): int => (int) $balance)
            ->all();
    }

    /** @return Collection<int, \stdClass> */
    public function forProfile(FinancialProfile $profile): Collection
    {
        return DB::table('money_movements')
            ->join('obligations', 'obligations.id', '=', 'money_movements.obligation_id')
            ->join('records', 'records.id', '=', 'obligations.record_id')
            ->where('records.profile_id', $profile->getKey())
            ->where('records.is_archived', false)
            ->where('money_movements.status', MovementStatus::Confirmed->value)
            ->selectRaw(
                'obligations.id, obligations.direction, money_movements.currency, '.$this->balanceExpression().' AS balance',
                $this->balanceBindings(),
            )
            ->groupBy('obligations.id', 'obligations.direction', 'money_movements.currency')
            ->get();
    }

    /** @return list<string> */
    private function balanceBindings(): array
    {
        $bindings = [];
        foreach (MoneyEntry::cases() as $entry) {
            foreach (Direction::cases() as $direction) {
                $bindings[] = $entry->value;
                $bindings[] = $direction->value;
            }
        }

        return $bindings;
    }

    /** @return literal-string */
    private function balanceExpression(): string
    {
        $clauses = [];
        foreach (MoneyEntry::cases() as $entry) {
            foreach (Direction::cases() as $direction) {
                $effect = $entry->signedEffect($direction);
                $amount = $effect < 0 ? '-money_movements.amount_minor' : 'money_movements.amount_minor';
                $clauses[] = "WHEN money_movements.entry = ? AND obligations.direction = ? THEN {$amount}";
            }
        }

        return 'SUM(CASE '.implode(' ', $clauses).' ELSE 0 END)';
    }
}
