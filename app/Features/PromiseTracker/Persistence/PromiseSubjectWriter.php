<?php

namespace App\Features\PromiseTracker\Persistence;

use App\Features\PromiseTracker\Actions\Data\PromiseSubjectData;
use App\Features\PromiseTracker\Actions\Data\RecordMovementData;
use App\Features\PromiseTracker\Actions\Money\RecordMoneyMovement;
use App\Features\PromiseTracker\Domain\Enums\SubjectType;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\User;

final class PromiseSubjectWriter
{
    public function __construct(private readonly RecordMoneyMovement $recordMoneyMovement) {}

    public function handle(User $user, Obligation $obligation, PromiseSubjectData $data, ?string $note = null): void
    {
        match ($data->type) {
            SubjectType::Money => $this->createMoneySubject($user, $obligation, $data, $note),
            SubjectType::Quantity => $obligation->quantitySubject()->create([
                'name' => $data->quantityName,
                'total' => $data->quantityTotal,
                'unit' => $data->quantityUnit,
                'is_fractionable' => $data->isFractionable,
            ]),
            SubjectType::Commitment => $obligation->commitmentSubject()->create([
                'done_criteria' => $data->doneCriteria,
            ]),
        };
    }

    private function createMoneySubject(User $user, Obligation $obligation, PromiseSubjectData $data, ?string $note): void
    {
        $obligation->moneySubject()->create(['currency' => $data->currency]);
        $this->recordMoneyMovement->handle(
            $user,
            $obligation,
            RecordMovementData::opening($data->amountMinor, $data->currency, today()->toDateString(), $note),
        );
    }
}
