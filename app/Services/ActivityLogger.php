<?php

namespace App\Services;

use App\Models\ActivityEntry;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        FinancialProfile $profile,
        User $actor,
        Model $subject,
        string $action,
        ?array $before = null,
        ?array $after = null,
    ): ActivityEntry {
        return ActivityEntry::create([
            'profile_id' => $profile->getKey(),
            'actor_user_id' => $actor->getKey(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'occurred_at' => now(),
        ]);
    }
}
