<?php

namespace App\Services;

use App\Models\EmergencyAccessRequest;
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
        $members = $profile->members()
            ->select(['id', 'profile_id', 'user_id', 'role'])
            ->with(['user' => fn ($query) => $query->select(['id', 'name', 'email', 'email_verified_at'])])
            ->whereNull('revoked_at')
            ->whereNotNull('accepted_at')
            ->get();
        $heirUserIds = $members->where('role', 'heir')->pluck('user_id')->all();
        $activatedHeirUserIds = $heirUserIds === []
            ? collect()
            : EmergencyAccessRequest::query()
                ->where('profile_id', $profile->getKey())
                ->whereIn('user_id', $heirUserIds)
                ->where('status', 'activated')
                ->where(function ($query): void {
                    $query->whereNull('activate_after')->orWhere('activate_after', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->pluck('user_id');
        $recipients = collect([$profile->loadMissing(['owner' => fn ($query) => $query->select(['id', 'name', 'email', 'email_verified_at'])])->owner])
            ->merge($members
                ->filter(fn ($member): bool => $member->user !== null
                    && ($member->role !== 'heir' || $activatedHeirUserIds->contains($member->user_id)))
                ->map(fn ($member) => $member->user))
            ->filter()
            ->unique('id')
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $send = static function () use ($recipients, $notification): void {
            NotificationFacade::send($recipients, $notification);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($send);

            return;
        }

        $send();
    }

    /** @param array<string, mixed> $context */
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
