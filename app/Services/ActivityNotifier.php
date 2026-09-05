<?php

namespace App\Services;

use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Notifications\ObligationActivityNotification;
use App\Notifications\ProfileActivityNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class ActivityNotifier
{
    public function notifyProfile(FinancialProfile $profile, Notification $notification): void
    {
        $recipients = collect([$profile->owner])
            ->merge($profile->members()->with('user')->whereNull('revoked_at')->whereNotNull('accepted_at')->get()->map(fn ($member) => $member->user))
            ->filter(fn ($user): bool => $user !== null && app(ProfileAccess::class)->role($user, $profile) !== null)
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $send = static fn (): mixed => NotificationFacade::send($recipients, $notification);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }

    public function notifyObligation(
        Obligation $obligation,
        string $event,
        string $title,
        string $message,
        string $priority = 'normal',
        array $context = [],
    ): void {
        $this->notifyProfile(
            $obligation->record->profile,
            new ObligationActivityNotification($obligation, $event, $title, $message, $priority, $context),
        );
    }

    /** @param array<string, mixed> $context */
    public function notifyProfileActivity(
        FinancialProfile $profile,
        string $event,
        string $title,
        string $message,
        string $priority = 'normal',
        array $context = [],
    ): void {
        $this->notifyProfile(
            $profile,
            new ProfileActivityNotification($profile, $event, $title, $message, $priority, $context),
        );
    }
}
