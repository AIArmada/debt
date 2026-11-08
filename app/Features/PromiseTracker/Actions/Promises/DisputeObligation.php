<?php

namespace App\Features\PromiseTracker\Actions\Promises;

use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\User;

final class DisputeObligation
{
    public function __construct(private readonly ChangeObligationStatus $changeStatus) {}

    public function handle(User $user, Obligation $obligation): Obligation
    {
        return $this->changeStatus->handle($user, $obligation, 'dispute');
    }
}
