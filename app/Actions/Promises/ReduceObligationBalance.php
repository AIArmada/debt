<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\ReduceObligationData;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReduceObligationBalance
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly RecordMoneyMovement $recordMoneyMovement,
    ) {}

    /** @return Collection<int, MoneyMovement> */
    public function handle(User $user, Obligation $obligation, ReduceObligationData $data, MoneyEntry $entry): Collection
    {
        $obligation->loadMissing('record.profile', 'moneySubject');
        Gate::forUser($user)->authorize('recordMovement', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data, $entry): Collection {
            $locked = Obligation::query()->whereKey($obligation->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('record.profile', 'moneySubject');
            Gate::forUser($user)->authorize('recordMovement', $locked);

            if ($locked->subject_type !== SubjectType::Money) {
                throw ValidationException::withMessages(['obligation' => 'Only money promises can be forgiven or written off.']);
            }

            $balances = $this->outstandingBalance->forObligation($locked);
            $targets = $data->amountMinor === null
                ? array_filter($balances, static fn (int $balance): bool => $balance !== 0)
                : [$data->currency => $balances[$data->currency] ?? 0];

            if ($targets === []) {
                throw ValidationException::withMessages(['amount' => 'This promise has no outstanding balance to reduce.']);
            }

            $movements = new Collection;
            foreach ($targets as $currency => $balance) {
                $amountMinor = $data->amountMinor ?? abs($balance);
                if ($balance === 0 || $amountMinor > abs($balance)) {
                    throw ValidationException::withMessages(['amount' => 'The amount cannot exceed the outstanding balance in that currency.']);
                }

                $movements->push($this->recordMoneyMovement->handle($user, $locked, RecordMovementData::confirmed(
                    $amountMinor,
                    (string) $currency,
                    $entry,
                    today()->toDateString(),
                    $data->note,
                    ['signed_effect' => $entry->signedEffectForBalance($locked->direction, $balance)],
                    $entry === MoneyEntry::Forgiveness ? 'obligation_forgiven' : 'obligation_written_off',
                )));
            }

            return $movements;
        });
    }
}
