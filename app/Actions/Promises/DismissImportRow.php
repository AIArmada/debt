<?php

namespace App\Actions\Promises;

use App\Domain\Enums\ImportRowStatus;
use App\Domain\Enums\MemberRole;
use App\Models\ImportRow;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DismissImportRow
{
    public function __construct(private readonly ProfileAccess $profileAccess) {}

    public function handle(User $user, ImportRow $row): ImportRow
    {
        $row->loadMissing('batch.profile');
        if (! $this->profileAccess->can($user, $row->batch->profile, [MemberRole::Owner, MemberRole::Editor])) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($row): ImportRow {
            $locked = ImportRow::query()->whereKey($row->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== ImportRowStatus::Pending) {
                throw ValidationException::withMessages(['row' => 'Only pending import rows can be dismissed.']);
            }
            $locked->forceFill(['status' => ImportRowStatus::Dismissed])->save();

            return $locked->refresh();
        });
    }
}
