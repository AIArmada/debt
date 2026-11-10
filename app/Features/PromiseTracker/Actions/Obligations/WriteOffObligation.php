<?php

namespace App\Features\PromiseTracker\Actions\Obligations;

use App\Features\PromiseTracker\Actions\Data\ReduceObligationData;
use App\Features\PromiseTracker\Domain\Enums\MoneyEntry;
use App\Features\PromiseTracker\Models\MoneyMovement;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class WriteOffObligation
{
    public function __construct(private readonly ReduceObligationBalance $reduceBalance) {}

    /** @return Collection<int, MoneyMovement> */
    public function handle(User $user, Obligation $obligation, ReduceObligationData $data): Collection
    {
        return $this->reduceBalance->handle($user, $obligation, $data, MoneyEntry::WriteOff);
    }
}
