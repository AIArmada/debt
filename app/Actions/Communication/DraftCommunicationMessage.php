<?php

namespace App\Actions\Communication;

use App\Models\CommunicationMessage;
use App\Models\CommunicationThread;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DraftCommunicationMessage
{
    public function handle(User $user, Obligation $obligation, string $channel, string $recipient, string $body): CommunicationMessage
    {
        Gate::forUser($user)->authorize('manageCommunication', $obligation);

        $thread = DB::transaction(function () use ($obligation, $channel): CommunicationThread {
            CommunicationThread::query()->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'obligation_id' => $obligation->getKey(),
                'channel' => $channel,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return CommunicationThread::query()
                ->where('obligation_id', $obligation->getKey())
                ->where('channel', $channel)
                ->lockForUpdate()
                ->firstOrFail();
        });

        return $thread->messages()->create([
            'created_by_user_id' => $user->getKey(),
            'direction' => 'outgoing',
            'status' => 'draft',
            'recipient' => $recipient ?: null,
            'body' => $body,
        ]);
    }
}
