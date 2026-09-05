<?php

namespace App\Actions\Communication;

use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DraftCommunicationMessage
{
    public function handle(User $user, Obligation $obligation, string $channel, string $recipient, string $body): CommunicationMessage
    {
        Gate::forUser($user)->authorize('manageCommunication', $obligation);

        $thread = CommunicationThread::firstOrCreate(
            ['obligation_id' => $obligation->getKey(), 'channel' => $channel],
            ['status' => 'open'],
        );

        return $thread->messages()->create([
            'created_by_user_id' => $user->getKey(),
            'direction' => 'outgoing',
            'status' => 'draft',
            'recipient' => $recipient ?: null,
            'body' => $body,
        ]);
    }
}
