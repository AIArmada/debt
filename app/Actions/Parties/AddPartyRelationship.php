<?php

namespace App\Actions\Parties;

use App\Actions\Parties\Data\PartyRelationshipData;
use App\Domain\Enums\PartyStatus;
use App\Models\Party;
use App\Models\PartyRelationship;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AddPartyRelationship
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Party $fromParty, PartyRelationshipData $data): PartyRelationship
    {
        $fromParty->loadMissing('profile');
        Gate::forUser($user)->authorize('manageRelationships', $fromParty);

        if ($fromParty->getKey() === $data->toPartyId) {
            throw ValidationException::withMessages(['relationship' => 'A person cannot be linked to themselves.']);
        }

        $toParty = $fromParty->profile->parties()
            ->whereKey($data->toPartyId)
            ->where('status', PartyStatus::Active->value)
            ->first();

        if (! $toParty instanceof Party) {
            throw ValidationException::withMessages(['relationship' => 'Choose a person from this profile.']);
        }

        try {
            return DB::transaction(function () use ($user, $fromParty, $data): PartyRelationship {
                $relationship = PartyRelationship::query()->create([
                    'profile_id' => $fromParty->profile_id,
                    'from_party_id' => $fromParty->getKey(),
                    'to_party_id' => $data->toPartyId,
                    'kind' => $data->kind,
                    'created_by' => $user->getKey(),
                ]);
                $fromParty->loadMissing('profile');
                $this->activityLogger->record($fromParty->profile, $user, $relationship, 'party_relationship_added');

                return $relationship->refresh();
            });
        } catch (QueryException $exception) {
            $isUniqueViolation = $exception->getCode() === '23505'
                || ($exception->errorInfo[0] ?? null) === '23505';
            if (! $isUniqueViolation || ! str_contains($exception->getMessage(), 'party_relationships_from_party_id_to_party_id_kind_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['relationship' => 'This relationship already exists.']);
        }
    }
}
