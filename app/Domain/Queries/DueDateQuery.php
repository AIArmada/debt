<?php

namespace App\Domain\Queries;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\ReminderStatus;
use App\Models\Record;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class DueDateQuery
{
    /**
     * @param  Builder<Record>  $records
     * @return Builder<Record>
     */
    public function overdue(Builder $records): Builder
    {
        return $records
            ->where('is_archived', false)
            ->whereHas('obligations', function (Builder $obligations): void {
                $this->applyOverdueDefinition($obligations);
            });
    }

    /**
     * @param  Builder<Record>  $records
     * @return Builder<Record>
     */
    public function dueSoon(Builder $records): Builder
    {
        $from = today()->toDateString();
        $until = today()->addDays(7)->toDateString();

        return $records
            ->where('is_archived', false)
            ->whereDoesntHave('obligations', function (Builder $obligations): void {
                $this->applyOverdueDefinition($obligations);
            })
            ->whereHas('obligations', function (Builder $obligations) use ($from, $until): void {
                $obligations
                    ->where('status', ObligationStatus::Open->value)
                    ->where(function (Builder $query) use ($from, $until): void {
                        $query->whereBetween('due_on', [$from, $until])
                            ->orWhereHas('reminders', function (Builder $reminders) use ($from, $until): void {
                                $reminders
                                    ->whereIn('status', [ReminderStatus::Pending->value, ReminderStatus::Snoozed->value])
                                    ->whereRaw('COALESCE(snoozed_until, remind_on) BETWEEN ? AND ?', [$from, $until]);
                            });
                    });
            });
    }

    public function recordIsOverdue(Record $record): bool
    {
        return $this->overdue(Record::query()->whereKey($record->getKey()))->exists();
    }

    /** @param Builder<Model> $obligations */
    private function applyOverdueDefinition(Builder $obligations): void
    {
        $obligations
            ->where('status', ObligationStatus::Open->value)
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', today()->toDateString());
    }
}
