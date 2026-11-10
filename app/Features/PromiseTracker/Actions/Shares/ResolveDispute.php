<?php

namespace App\Features\PromiseTracker\Actions\Shares;

use App\Features\PromiseTracker\Actions\Obligations\ChangeObligationStatus;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\User;

final class ResolveDispute
{
    public function __construct(private readonly ChangeObligationStatus $changeStatus) {}

    public function handle(User $user, Obligation $obligation): Obligation
    {
        return $this->changeStatus->handle($user, $obligation, 'resolve_dispute');
    }
}
