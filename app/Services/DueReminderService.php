<?php

namespace App\Services;

use App\Models\EmergencyAccessRequest;
use App\Models\FinancialProfile;
use App\Notifications\DebtDueNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class DueReminderService
{
    public function send(): int
    {
        $sent = 0;
        $from = CarbonImmutable::today();
        $until = $from->addDays(30);
        FinancialProfile::query()
            ->where('is_archived', false)
            ->whereHas('records.obligations', fn ($query) => $query
                ->where('status', 'active')
                ->whereNotNull('next_due_on')
                ->whereBetween('next_due_on', [$from->toDateString(), $until->toDateString()])
                ->where(fn ($query) => $query->where('is_conditional', false)->orWhereNotNull('condition_triggered_on')))
            ->select(['id', 'owner_user_id', 'timezone'])
            ->with([
                'owner:id,name,email,email_verified_at',
                'owner.notificationPreference',
                'members' => fn ($query) => $query
                    ->whereNotNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->select(['id', 'profile_id', 'user_id', 'role'])
                    ->with(['user:id,name,email,email_verified_at', 'user.notificationPreference']),
                'records' => fn ($query) => $query->select(['id', 'profile_id']),
                'records.obligations' => fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNotNull('next_due_on')
                    ->whereBetween('next_due_on', [$from->toDateString(), $until->toDateString()])
                    ->notDormant()
                    ->select(['id', 'record_id', 'next_due_on']),
                'records.obligations.record:id,profile_id',
            ])
            ->chunkById(50, function (Collection $profiles) use (&$sent): void {
                $heirMembers = $profiles
                    ->flatMap(fn (FinancialProfile $profile) => $profile->members->where('role', 'heir'));
                $activatedHeirs = $heirMembers->isEmpty()
                    ? collect()
                    : EmergencyAccessRequest::query()
                        ->whereIn('profile_id', $profiles->modelKeys())
                        ->whereIn('user_id', $heirMembers->pluck('user_id')->all())
                        ->where('status', 'activated')
                        ->where(function ($query): void {
                            $query->whereNull('activate_after')->orWhere('activate_after', '<=', now());
                        })
                        ->where(function ($query): void {
                            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->get(['profile_id', 'user_id'])
                        ->mapWithKeys(fn ($request): array => [(string) $request->profile_id.'|'.(string) $request->user_id => true]);

                foreach ($profiles as $profile) {
                    $today = CarbonImmutable::now($profile->timezone ?? 'UTC')->startOfDay();
                    $recipients = collect([$profile->owner])
                        ->merge($profile->members
                            ->filter(fn ($member): bool => $member->user !== null
                                && ($member->role !== 'heir' || $activatedHeirs->has((string) $profile->getKey().'|'.(string) $member->user_id)))
                            ->map(fn ($member) => $member->user))
                        ->filter()
                        ->unique('id');
                    foreach ($recipients as $user) {
                        $preference = $user->notificationPreference;
                        if ($preference === null) {
                            $preference = $user->notificationPreference()->firstOrCreate(['user_id' => $user->getKey()], ['email_enabled' => true, 'in_app_enabled' => true, 'push_enabled' => false, 'generic_push' => true, 'due_reminder_days' => 3]);
                        }
                        if (! $preference->in_app_enabled
                            && ! ($preference->email_enabled && $user->email_verified_at !== null)
                            && ! $preference->push_enabled) {
                            continue;
                        }
                        $days = (int) $preference->due_reminder_days;
                        foreach ($profile->records->flatMap(fn ($record) => $record->obligations) as $obligation) {
                            $dueOn = $obligation->next_due_on?->toDateString();
                            $recipientUntil = $today->addDays($days)->toDateString();
                            if (! is_string($dueOn) || $dueOn < $today->toDateString() || $dueOn > $recipientUntil) {
                                continue;
                            }
                            $deduplicationKey = 'due:'.$obligation->getKey().':'.$today->toDateString();
                            // Compare in UTC instants: created_at is stored in UTC while
                            // the profile day can straddle two UTC dates near midnight.
                            $dayStartUtc = $today->copy()->setTimezone('UTC');
                            $alreadySent = $user->notifications()
                                ->where('type', DebtDueNotification::class)
                                ->whereBetween('created_at', [$dayStartUtc->toDateTimeString(), $dayStartUtc->addDay()->toDateTimeString()])
                                ->where('data', 'like', '%'.$deduplicationKey.'%')
                                ->exists();
                            if ($alreadySent) {
                                continue;
                            }
                            $user->notify(new DebtDueNotification($obligation));
                            $sent++;
                        }
                    }
                }
            });

        return $sent;
    }
}
