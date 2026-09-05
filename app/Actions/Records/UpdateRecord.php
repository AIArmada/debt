<?php

namespace App\Actions\Records;

use App\Models\Party;
use App\Models\Record;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateRecord
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array{title: string, party_id: string|null, description: string, sensitivity: string} $data */
    public function handle(Record $record, array $data): Record
    {
        $record->load('profile');
        Gate::authorize('update', $record);

        $party = null;
        if (filled($data['party_id'] ?? null)) {
            $party = Party::query()
                ->whereKey($data['party_id'])
                ->where('profile_id', $record->profile_id)
                ->whereNull('archived_at')
                ->first();

            if ($party === null) {
                throw ValidationException::withMessages(['party_id' => 'Choose a party from this profile.']);
            }
        }

        $before = [
            ...$record->only(['title', 'description', 'sensitivity', 'is_archived']),
            'party_ids' => $record->partyLinks()->pluck('party_id')->all(),
        ];

        DB::transaction(function () use ($record, $data, $party): void {
            $record->forceFill([
                'title' => $data['title'],
                'description' => $data['description'] ?: null,
                'sensitivity' => $data['sensitivity'],
            ])->save();

            $record->partyLinks()->where('role', 'other_party')->where('is_primary', true)->update(['is_primary' => false]);

            if ($party !== null) {
                $record->partyLinks()->updateOrCreate(
                    ['party_id' => $party->getKey(), 'role' => 'other_party'],
                    [
                        'created_by_user_id' => auth()->id(),
                        'is_primary' => true,
                        'responsibility_scope' => 'record',
                        'status' => 'active',
                        'visibility' => $record->sensitivity === 'shared' ? 'shared' : 'restricted',
                    ],
                );
            }
        });

        $this->auditLogger->record(
            $record->profile,
            null,
            Record::class,
            $record->getKey(),
            'updated',
            before: $before,
            after: [...$record->refresh()->only(['title', 'description', 'sensitivity', 'is_archived']), 'party_ids' => $record->partyLinks()->pluck('party_id')->all()],
        );

        return $record->refresh()->load(['profile', 'partyLinks.party', 'obligations']);
    }
}
