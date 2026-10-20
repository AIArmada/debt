<?php

namespace App\Domain\Queries;

use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\ReminderStatus;
use App\Models\Record;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
                        $query->where(function (Builder $withoutInstalments) use ($from, $until): void {
                            $withoutInstalments->whereDoesntHave('instalments')->whereBetween('due_on', [$from, $until]);
                        })->orWhere(function (Builder $withInstalments) use ($from, $until): void {
                            $this->whereNextUnpaidInstalment($withInstalments, static function (Builder $instalments) use ($from, $until): void {
                                $instalments->whereBetween('due_on', [$from, $until]);
                            });
                        })->orWhereHas('reminders', function (Builder $reminders) use ($from, $until): void {
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
            ->where(function (Builder $query): void {
                $query->where(function (Builder $withoutInstalments): void {
                    $withoutInstalments->whereDoesntHave('instalments')
                        ->whereNotNull('due_on')
                        ->whereDate('due_on', '<', today()->toDateString());
                })->orWhere(function (Builder $withInstalments): void {
                    $this->whereNextUnpaidInstalment($withInstalments, static function (Builder $instalments): void {
                        $instalments->whereDate('due_on', '<', today()->toDateString());
                    });
                });
            });
    }

    /**
     * @param  Builder<Model>  $obligations
     * @param  Closure(Builder<Model>): void  $dateFilter
     */
    private function whereNextUnpaidInstalment(Builder $obligations, Closure $dateFilter): void
    {
        $obligations->whereHas('instalments', function (Builder $instalments) use ($dateFilter): void {
            $instalments
                ->whereColumn('paid_minor', '<', 'amount_minor');
            $dateFilter($instalments);
            $instalments->whereNotExists(function (QueryBuilder $earlier): void {
                $earlier->selectRaw('1')
                    ->from('instalments as earlier_instalments')
                    ->whereColumn('earlier_instalments.obligation_id', 'instalments.obligation_id')
                    ->whereColumn('earlier_instalments.sequence', '<', 'instalments.sequence')
                    ->whereColumn('earlier_instalments.paid_minor', '<', 'earlier_instalments.amount_minor');
            });
        });
    }
}
