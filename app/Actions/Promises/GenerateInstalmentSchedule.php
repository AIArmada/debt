<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\GenerateInstalmentScheduleData;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Models\Instalment;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\ObligationChargeTerm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class GenerateInstalmentSchedule
{
    public function __construct(private readonly OutstandingBalance $outstandingBalance) {}

    /** @return array<int, Instalment> */
    public function handle(User $user, Obligation $obligation, GenerateInstalmentScheduleData $data): array
    {
        $obligation->loadMissing('record.profile', 'moneySubject', 'chargeTerms');
        Gate::forUser($user)->authorize('update', $obligation->record);

        return DB::transaction(function () use ($user, $obligation, $data): array {
            $locked = Obligation::query()
                ->whereKey($obligation->getKey())
                ->with(['record.profile', 'moneySubject', 'chargeTerms'])
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($user)->authorize('update', $locked->record);

            if ($locked->subject_type !== SubjectType::Money || $locked->moneySubject === null) {
                throw ValidationException::withMessages(['schedule' => 'Instalments are available for money promises only.']);
            }
            if ($locked->status !== ObligationStatus::Open) {
                throw ValidationException::withMessages(['schedule' => 'Only open promises can have an instalment schedule.']);
            }
            if ($locked->instalments()->exists()) {
                throw ValidationException::withMessages(['schedule' => 'Use Regenerate to replace an existing schedule.']);
            }

            $currency = (string) $locked->moneySubject->currency;
            $this->outstandingBalance->forget($locked);
            $balance = $this->outstandingBalance->forObligation($locked)[$currency] ?? 0;
            $totalMinor = abs($balance) + $this->unpostedFixedTotalProfit($locked, $currency);
            if ($totalMinor <= 0) {
                throw ValidationException::withMessages(['schedule' => 'A schedule needs a positive outstanding balance.']);
            }
            if ($data->count > $totalMinor) {
                throw ValidationException::withMessages(['count' => 'The instalment count is too high for this amount.']);
            }

            $baseAmount = intdiv($totalMinor, $data->count);
            $remainder = $totalMinor % $data->count;
            $dates = $this->dueDates($data, $locked->record->profile->timezone);
            $instalments = [];

            foreach ($dates as $index => $dueOn) {
                $amount = $baseAmount + ($index === $data->count - 1 ? $remainder : 0);
                $instalments[] = $locked->instalments()->create([
                    'sequence' => $index + 1,
                    'due_on' => $dueOn,
                    'amount_minor' => $amount,
                    'currency' => $currency,
                    'paid_minor' => 0,
                ]);
            }

            return $instalments;
        });
    }

    private function unpostedFixedTotalProfit(Obligation $obligation, string $currency): int
    {
        $terms = $obligation->chargeTerms;
        if (! $terms instanceof ObligationChargeTerm || $terms->basis !== 'fixed_total' || $terms->total_payable_minor === null) {
            return 0;
        }
        if ($terms->fixed_total_posted_at !== null) {
            return 0;
        }

        $principalMinor = (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->whereIn('entry', [MoneyEntry::OpeningBalance->value, MoneyEntry::Advance->value])
            ->sum('amount_minor');
        $expectedProfit = max(0, (int) $terms->total_payable_minor - $principalMinor);
        $confirmedProfit = (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Confirmed->value)
            ->where('entry', MoneyEntry::ProfitCharge->value)
            ->whereJsonContains('calculation', ['fixed_total' => true])
            ->sum('amount_minor');
        $proposedProfit = (int) MoneyMovement::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $currency)
            ->where('status', MovementStatus::Proposed->value)
            ->where('entry', MoneyEntry::ProfitCharge->value)
            ->whereJsonContains('calculation', ['fixed_total' => true])
            ->sum('amount_minor');

        return max(0, max($expectedProfit, $proposedProfit) - $confirmedProfit);
    }

    /** @return list<string> */
    private function dueDates(GenerateInstalmentScheduleData $data, string $timezone): array
    {
        if ($data->payDayOfMonth !== null) {
            $today = CarbonImmutable::today($timezone);
            $first = $today->startOfMonth()->setDay($data->payDayOfMonth);
            if ($first->isBefore($today)) {
                $first = $first->addMonthNoOverflow();
            }
        } else {
            $first = CarbonImmutable::parse((string) $data->firstDueOn)->startOfDay();
        }

        $dates = [];
        for ($index = 0; $index < $data->count; $index++) {
            if ($data->frequency === 'weekly') {
                $date = $first->addWeeks($index);
            } else {
                $month = $first->startOfMonth()->addMonths($index);
                $day = min((int) $first->day, $month->daysInMonth);
                $date = $month->setDay($day);
            }
            $dates[] = $date->toDateString();
        }

        return $dates;
    }
}
