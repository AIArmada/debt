<?php

namespace App\Console\Commands;

use App\Domain\Enums\MemberRole;
use App\Domain\Enums\NotificationType;
use App\Domain\Enums\ReminderStatus;
use App\Domain\Queries\OutstandingBalance;
use App\Models\FinancialProfile;
use App\Models\ProfileMember;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\ReminderDue;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class SendDueReminders extends Command
{
    protected $signature = 'reminders:send-due';

    protected $description = 'Create database notifications for due promise reminders';

    public function handle(OutstandingBalance $outstandingBalance): int
    {
        $count = 0;

        $timezones = FinancialProfile::query()->distinct()->orderBy('timezone')->pluck('timezone');
        foreach ($timezones as $timezone) {
            $today = Carbon::now((string) $timezone)->toDateString();

            Reminder::query()
                ->whereIn('status', [ReminderStatus::Pending->value, ReminderStatus::Snoozed->value])
                ->whereRaw('COALESCE(snoozed_until, remind_on) <= ?', [$today])
                ->whereHas('obligation.record.profile', function (Builder $query) use ($timezone): void {
                    $query->where('timezone', $timezone);
                })
                ->with(['obligation.record.profile.members'])
                ->eachById(function (Reminder $reminder) use (&$count, $outstandingBalance): void {
                    DB::transaction(function () use ($reminder, &$count, $outstandingBalance): void {
                        $locked = Reminder::query()->whereKey($reminder->getKey())->lockForUpdate()->first();
                        if ($locked === null || ! in_array($locked->status, [ReminderStatus::Pending, ReminderStatus::Snoozed], true)) {
                            return;
                        }

                        $locked->loadMissing(['obligation.record.profile.members']);
                        $obligation = $locked->obligation;
                        $record = $obligation->record;
                        $profile = $record->profile;
                        $recipients = $this->recipients($locked, $profile);

                        if ($recipients->isNotEmpty()) {
                            Notification::send($recipients, new ReminderDue($this->payload($locked, $outstandingBalance)));
                            $count += $recipients->count();
                        }

                        $locked->forceFill(['status' => ReminderStatus::Sent])->save();
                    });
                });
        }

        $this->info("Created {$count} reminder notification(s).");

        return self::SUCCESS;
    }

    /** @return Collection<int, User> */
    private function recipients(Reminder $reminder, FinancialProfile $profile): Collection
    {
        $recipientIds = collect([$profile->owner_user_id]);
        $writableMembers = $profile->members->filter(function (ProfileMember $member): bool {
            return $member->accepted_at !== null
                && $member->revoked_at === null
                && in_array($member->role, [MemberRole::Owner, MemberRole::Editor], true);
        });
        $recipientIds = $recipientIds->merge($writableMembers->pluck('user_id'));

        $creatorIsWritableMember = $profile->owner_user_id === $reminder->created_by
            || $writableMembers->contains(fn (ProfileMember $member): bool => $member->user_id === $reminder->created_by);
        if ($creatorIsWritableMember) {
            $recipientIds->push($reminder->created_by);
        }

        return User::query()
            ->whereIn('id', $recipientIds->unique()->values())
            ->whereDoesntHave('notificationPreferences', function (Builder $query): void {
                $query->where('type', NotificationType::ReminderDue->value)->where('enabled', false);
            })
            ->get();
    }

    /** @return array<string, mixed> */
    private function payload(Reminder $reminder, OutstandingBalance $outstandingBalance): array
    {
        $obligation = $reminder->obligation;
        $record = $obligation->record;
        $amountSnapshot = [];

        foreach ($outstandingBalance->forObligation($obligation) as $currency => $amount) {
            if ($amount !== 0) {
                $amountSnapshot[$currency] = abs($amount);
            }
        }

        return [
            'reminder_id' => $reminder->getKey(),
            'profile_id' => $record->profile->getKey(),
            'profile_name' => $record->profile->name,
            'record_id' => $record->getKey(),
            'title' => $record->title,
            'amount_snapshot' => $amountSnapshot,
            'direction_snapshot' => $obligation->direction->value,
            'due_on_snapshot' => $obligation->due_on?->toDateString(),
        ];
    }
}
