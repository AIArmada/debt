<?php

namespace App\Services;

use App\Models\FinancialProfile;
use App\Models\SupportAuditEntry;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SupportAuditLogger
{
    private ?SupportAuditEntry $pendingEntry = null;

    /** @param array<string, mixed> $metadata */
    public function record(
        User $actor,
        string $action,
        ?SupportCase $case = null,
        ?FinancialProfile $profile = null,
        ?Model $subject = null,
        array $metadata = [],
    ): SupportAuditEntry {
        foreach ($metadata as $value) {
            if (! is_string($value) && ! is_int($value) && ! is_bool($value) && $value !== null) {
                throw new InvalidArgumentException('Support audit metadata values must be strings, integers, booleans, or null.');
            }
        }

        $redacted = [];

        foreach (['resource', 'count', 'status', 'grant_id', 'case_id', 'invite_id', 'result'] as $key) {
            if (array_key_exists($key, $metadata)) {
                $value = $metadata[$key];
                $redacted[$key] = is_string($value) ? mb_substr($value, 0, 120) : $value;
            }
        }

        $entry = new SupportAuditEntry;
        $entry->forceFill([
            'actor_id' => $actor->getKey(),
            'case_id' => $case?->getKey(),
            'profile_id' => $profile?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $redacted,
            'created_at' => now(),
        ]);

        $this->pendingEntry = $entry;

        try {
            $entry->save();

            return $entry;
        } finally {
            $this->pendingEntry = null;
        }
    }

    public function isCreatingEntry(SupportAuditEntry $entry): bool
    {
        return $this->pendingEntry === $entry;
    }
}
