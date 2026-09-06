<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\SettleObligationData;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Queries\OutstandingBalance;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Support\Collection;

final class SettleObligation
{
    public function __construct(
        private readonly OutstandingBalance $outstandingBalance,
        private readonly RecordMoneyMovement $recordMoneyMovement,
    ) {}

    /** @return Collection<int, MoneyMovement> */
    public function handle(User $user, Obligation $obligation, SettleObligationData $data): Collection
    {
        $balances = $this->outstandingBalance->forObligation($obligation);
        $movements = new Collection;
        foreach ($balances as $currency => $balance) {
            if ($balance === 0) {
                continue;
            }

            $entry = MoneyEntry::settlementFor($obligation->direction, $balance);
            $movements->push($this->recordMoneyMovement->handle(
                $user,
                $obligation,
                RecordMovementData::confirmed(abs($balance), $currency, $entry, today()->toDateString(), $data->note),
            ));
        }

        return $movements;
    }
}
