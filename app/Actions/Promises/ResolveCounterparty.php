<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\ResolveCounterpartyData;
use App\Domain\Enums\PartyStatus;
use App\Models\FinancialProfile;
use App\Models\Party;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ResolveCounterparty
{
    public function handle(FinancialProfile $profile, ResolveCounterpartyData $data): Party
    {
        $normalisedName = Str::lower($data->name);

        if ($data->partyId !== null) {
            $party = $profile->parties()
                ->whereKey($data->partyId)
                ->where('status', PartyStatus::Active->value)
                ->first();

            if (! $party instanceof Party) {
                throw ValidationException::withMessages(['name' => 'Choose a person from this profile.']);
            }

            return $party;
        }

        $existing = $profile->parties()
            ->where('status', PartyStatus::Active->value)
            ->whereRaw('LOWER(display_name) = ?', [$normalisedName])
            ->first();

        if ($existing instanceof Party) {
            return $existing;
        }

        try {
            return $profile->parties()->create([
                'kind' => $data->kind,
                'display_name' => $data->name,
                'status' => PartyStatus::Active,
            ]);
        } catch (QueryException $exception) {
            $isUniqueViolation = $exception->getCode() === '23505'
                || ($exception->errorInfo[0] ?? null) === '23505';
            if (! $isUniqueViolation || ! str_contains($exception->getMessage(), 'parties_profile_display_name_active_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'name' => 'This person was added by another request. Choose them from the list.',
            ]);
        }
    }
}
