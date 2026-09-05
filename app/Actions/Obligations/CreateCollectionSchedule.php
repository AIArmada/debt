<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Models\CollectionAccount;
use App\Models\CollectionSchedule;
use App\Models\Obligation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateCollectionSchedule
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{mode: string, amount: string, currency: string, frequency: string, starts_on: string, next_due_on: string, ends_on: string|null, collection_method: string, collection_account_id: string|null, grace_days: int, note: string|null} $data */
    public function handle(User $user, Obligation $obligation, array $data): CollectionSchedule
    {
        Gate::forUser($user)->authorize('manageSchedule', $obligation);

        if ($obligation->obligation_kind !== 'money') {
            throw ValidationException::withMessages(['amount' => 'Collection schedules are available only for money obligations.']);
        }

        $currency = strtoupper($data['currency']);
        if (! Currency::isSupported($currency)) {
            throw ValidationException::withMessages(['currency' => 'Choose a supported currency.']);
        }

        if ($obligation->currencyPosition($currency)['direction'] !== 'receivable') {
            throw ValidationException::withMessages(['currency' => 'Choose a currency exposure that currently shows money you expect to receive.']);
        }

        try {
            $amount = MoneyAmount::fromMajor($data['amount'], $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        if (($data['ends_on'] ?? null) !== null && $data['ends_on'] < $data['starts_on']) {
            throw ValidationException::withMessages(['ends_on' => 'The end date cannot be before the start date.']);
        }

        $profile = $obligation->record->profile;
        $collectionAccountId = $data['collection_account_id'] ?? null;
        if ($collectionAccountId !== null && ! CollectionAccount::query()->whereKey($collectionAccountId)->where('profile_id', $profile->getKey())->where('currency', $currency)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['collection_account_id' => 'Choose an active collection account in the same currency.']);
        }

        return DB::transaction(function () use ($user, $obligation, $data, $amount, $currency, $collectionAccountId): CollectionSchedule {
            $schedule = $obligation->collectionSchedules()->create([
                'created_by_user_id' => $user->getKey(),
                'collection_account_id' => $collectionAccountId,
                'mode' => $data['mode'],
                'status' => 'active',
                'amount' => $amount,
                'currency' => $currency,
                'frequency' => $data['frequency'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'next_due_on' => $data['next_due_on'],
                'collection_method' => $data['collection_method'],
                'grace_days' => $data['grace_days'],
                'note' => $data['note'] ?? null,
            ]);

            $this->auditLogger->record(
                $obligation->record->profile,
                $user,
                CollectionSchedule::class,
                $schedule->getKey(),
                'created',
                after: $schedule->only(['obligation_id', 'mode', 'status', 'amount', 'currency', 'frequency', 'starts_on', 'next_due_on', 'collection_method', 'grace_days']),
            );

            return $schedule;
        });
    }
}
