<?php

namespace App\Features\PromiseTracker\Actions\Imports;

use App\Features\PromiseTracker\Actions\Data\RecordMovementData;
use App\Features\PromiseTracker\Actions\Money\RecordMoneyMovement;
use App\Features\PromiseTracker\Domain\Enums\ImportRowStatus;
use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Domain\Enums\MoneyEntry;
use App\Features\PromiseTracker\Models\ImportRow;
use App\Features\PromiseTracker\Models\MoneyMovement;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmImportRow
{
    public function __construct(private readonly ProfileAccess $profileAccess, private readonly RecordMoneyMovement $recordMoneyMovement) {}

    public function handle(User $user, ImportRow $row): MoneyMovement
    {
        $row->loadMissing('batch.profile', 'suggestedObligation');
        $profile = $row->batch->profile;
        if (! $this->profileAccess->can($user, $profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($user, $row): MoneyMovement {
            $lockedRow = ImportRow::query()->whereKey($row->getKey())->lockForUpdate()->firstOrFail();
            $lockedRow->loadMissing('batch.profile', 'suggestedObligation');
            if ($lockedRow->status !== ImportRowStatus::Pending || $lockedRow->suggestedObligation === null || $lockedRow->suggested_entry === null) {
                throw ValidationException::withMessages(['row' => 'Match a pending import row before confirming it.']);
            }

            $movement = $this->recordMoneyMovement->handle($user, $lockedRow->suggestedObligation, RecordMovementData::confirmed(
                $lockedRow->amount_minor,
                $lockedRow->currency,
                MoneyEntry::from($lockedRow->suggested_entry),
                $lockedRow->occurred_on->toDateString(),
                $lockedRow->description,
            ));
            $lockedRow->forceFill(['status' => ImportRowStatus::Matched, 'matched_movement_id' => $movement->getKey()])->save();

            return $movement;
        });
    }
}
