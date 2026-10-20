<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\RecordGuarantorData;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PartyStatus;
use App\Models\Party;
use App\Models\Record;
use App\Models\RecordParty;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddRecordGuarantor
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Record $record, RecordGuarantorData $data): RecordParty
    {
        return DB::transaction(function () use ($user, $record, $data): RecordParty {
            $record->loadMissing('profile');
            Gate::forUser($user)->authorize('update', $record);

            if ($data->partyId !== null) {
                $party = Party::query()
                    ->whereKey($data->partyId)
                    ->where('profile_id', $record->profile_id)
                    ->where('status', PartyStatus::Active->value)
                    ->first();
                if (! $party instanceof Party) {
                    throw ValidationException::withMessages(['partyId' => 'Choose an active party from this profile.']);
                }
            } else {
                $party = Party::query()->create([
                    'profile_id' => $record->profile_id,
                    'kind' => PartyKind::Individual,
                    'display_name' => $data->partyName,
                    'status' => PartyStatus::Active,
                ]);
            }

            $existing = RecordParty::query()
                ->where('record_id', $record->getKey())
                ->where('party_id', $party->getKey())
                ->where('role', PartyRole::Guarantor->value)
                ->exists();
            if ($existing) {
                throw ValidationException::withMessages(['partyId' => 'This party is already listed as a guarantor.']);
            }

            $link = $record->partyLinks()->create([
                'party_id' => $party->getKey(),
                'role' => PartyRole::Guarantor,
                'is_primary' => false,
            ]);

            $this->activityLogger->record(
                $record->profile,
                $user,
                $link,
                'record_guarantor_added',
                after: ['party_id' => $party->getKey(), 'party_name' => $party->display_name],
            );

            return $link->load('party');
        });
    }
}
