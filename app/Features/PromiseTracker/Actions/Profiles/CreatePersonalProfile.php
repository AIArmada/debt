<?php

namespace App\Features\PromiseTracker\Actions\Profiles;

use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Support\Facades\DB;

final class CreatePersonalProfile
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $profile = $user->financialProfiles()->create([
                'name' => 'Personal',
                'base_currency' => 'MYR',
                'timezone' => 'Asia/Kuala_Lumpur',
            ]);
            $profile->members()->forceCreate([
                'user_id' => $user->getKey(),
                'role' => MemberRole::Owner,
                'accepted_at' => now(),
            ]);
        });
    }
}
