<?php

namespace App\Actions\Promises;

use App\Models\Obligation;
use App\Models\User;

final class ResumeObligation
{
    public function __construct(private readonly ChangeObligationStatus $changeStatus) {}

    public function handle(User $user, Obligation $obligation): Obligation
    {
        return $this->changeStatus->handle($user, $obligation, 'resume');
    }
}
