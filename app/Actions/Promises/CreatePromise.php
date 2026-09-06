<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\ResolveCounterpartyData;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Models\FinancialProfile;
use App\Models\Record;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreatePromise
{
    public function __construct(
        private readonly ResolveCounterparty $resolveCounterparty,
        private readonly PromiseSubjectWriter $subjectWriter,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, FinancialProfile $profile, CreatePromiseData $data): Record
    {
        Gate::forUser($user)->authorize('createObligation', $profile);

        return DB::transaction(function () use ($user, $profile, $data): Record {
            $party = $this->resolveCounterparty->handle($profile, ResolveCounterpartyData::fromInput([
                'name' => $data->partyName,
                'partyId' => $data->partyId,
                'kind' => PartyKind::Individual->value,
            ]));
            $record = $profile->records()->create([
                'title' => $party->display_name,
                'note' => $data->note,
                'is_archived' => false,
            ]);
            $record->partyLinks()->create([
                'party_id' => $party->getKey(),
                'role' => PartyRole::Counterparty->value,
                'is_primary' => true,
            ]);

            $obligation = $record->obligations()->create([
                'direction' => $data->direction->value,
                'title' => $record->title,
                'status' => ObligationStatus::Open,
                'subject_type' => $data->subject->type->value,
                'due_on' => $data->dueOn,
            ]);
            $this->subjectWriter->handle($user, $obligation, $data->subject, $data->note);

            $this->activityLogger->record(
                $profile,
                $user,
                $record,
                'promise_created',
                after: [
                    'record_id' => $record->getKey(),
                    'obligation_id' => $obligation->getKey(),
                    'party_id' => $party->getKey(),
                    'direction' => $data->direction->value,
                    'subject_type' => $data->subject->type->value,
                ],
            );

            return $record->load([
                'profile',
                'partyLinks.party',
                'obligations.moneySubject',
                'obligations.quantitySubject',
                'obligations.commitmentSubject',
                'obligations.moneyMovements',
            ]);
        });
    }
}
