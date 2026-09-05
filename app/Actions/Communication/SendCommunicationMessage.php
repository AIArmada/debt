<?php

namespace App\Actions\Communication;

use App\Mail\CommunicationMessageMail;
use App\Models\CommunicationMessage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class SendCommunicationMessage
{
    public function handle(User $user, CommunicationMessage $message, string $subject): CommunicationMessage
    {
        $message->load('thread.obligation');
        Gate::forUser($user)->authorize('manageCommunication', $message->thread->obligation);

        abort_unless($message->thread->channel === 'email' && filled($message->recipient), 422);

        Mail::to($message->recipient)->send(new CommunicationMessageMail($subject, $message->body));
        $message->status = 'sent';
        $message->sent_at = now();
        $message->save();

        return $message->refresh();
    }
}
