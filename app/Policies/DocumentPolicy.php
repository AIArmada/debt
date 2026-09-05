<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Support\Facades\Gate;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        $document->loadMissing('profile', 'links.record', 'links.obligation.record');
        $profile = $document->profile;
        $record = $document->links
            ->map(fn ($link) => $link->record ?? $link->obligation?->record)
            ->filter()
            ->first();

        if ($record !== null) {
            return Gate::forUser($user)->allows('view', $record);
        }

        return ! $profile->is_archived && app(ProfileAccess::class)->can($user, $profile, ['owner', 'editor', 'payment_manager', 'viewer', 'heir']);
    }
}
