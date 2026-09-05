<?php

namespace App\Actions\Records;

use App\Actions\Obligations\CreateObligation;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\Record;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateRecord
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CreateObligation $createObligation,
    ) {}

    /** @param array<string, mixed> $recordData @param array<string, mixed> $obligationData */
    public function handle(User $user, FinancialProfile $profile, array $recordData, array $obligationData): Record
    {
        Gate::forUser($user)->authorize('createObligation', $profile);

        return DB::transaction(function () use ($user, $profile, $recordData, $obligationData): Record {
            $party = $this->resolveParty($user, $profile, $recordData);
            $record = $profile->records()->create([
                'title' => $recordData['title'],
                'description' => $recordData['description'] ?: null,
                'sensitivity' => $recordData['sensitivity'] ?? 'private',
                'is_archived' => false,
            ]);

            if ($party !== null) {
                $record->partyLinks()->create([
                    'party_id' => $party->getKey(),
                    'created_by_user_id' => $user->getKey(),
                    'role' => 'other_party',
                    'is_primary' => true,
                    'responsibility_scope' => 'record',
                    'status' => 'active',
                    'visibility' => $record->sensitivity === 'shared' ? 'shared' : 'restricted',
                ]);
            }

            $obligation = $this->createObligation->handle($user, $record, $obligationData);

            if ($party !== null) {
                $obligation->partyLinks()->create([
                    'party_id' => $party->getKey(),
                    'created_by_user_id' => $user->getKey(),
                    'role' => $obligationData['direction'] === 'payable' ? 'beneficiary' : 'obligor',
                    'share_basis' => 'full',
                    'status' => 'active',
                ]);
            }

            $this->auditLogger->record(
                $profile,
                $user,
                Record::class,
                $record->getKey(),
                'created',
                after: [...$record->only(['profile_id', 'title', 'description', 'sensitivity']), 'party_id' => $party?->getKey()],
            );

            return $record->load(['profile', 'partyLinks.party', 'obligations.partyLinks.party', 'obligations']);
        });
    }

    /** @param array<string, mixed> $recordData */
    private function resolveParty(User $user, FinancialProfile $profile, array $recordData): ?Party
    {
        $partyId = $recordData['party_id'] ?? null;

        if (filled($partyId)) {
            $party = Party::query()
                ->whereKey($partyId)
                ->where('profile_id', $profile->getKey())
                ->whereNull('archived_at')
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages(['party_id' => 'Choose a party from this profile.']);
            }

            return $party;
        }

        $data = $recordData['party'] ?? null;
        if (! is_array($data) || blank($data['preferred_name'] ?? null)) {
            return null;
        }

        return $profile->parties()->create([
            'created_by_user_id' => $user->getKey(),
            'kind' => $data['kind'] ?? 'individual',
            'preferred_name' => trim((string) $data['preferred_name']),
            'legal_name' => filled($data['legal_name'] ?? null) ? trim((string) $data['legal_name']) : null,
            'status' => 'active',
            'verification_status' => 'unverified',
            'source' => 'record_creation',
        ]);
    }
}
