<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ReduceObligationData;
use App\Domain\Enums\MoneyEntry;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\User;
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
