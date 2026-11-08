<?php

namespace App\Features\PromiseTracker\Listeners;

use App\Features\PromiseTracker\Actions\Profiles\CreatePersonalProfile;
use App\Features\PromiseTracker\Models\User;

final class CreatePersonalProfileForUser
{
    public function __construct(private readonly CreatePersonalProfile $createPersonalProfile) {}

    public function handle(User $user): void
    {
        $this->createPersonalProfile->handle($user);
    }
}
