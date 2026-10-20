<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\GenerateInstalmentScheduleData;
use App\Models\Instalment;
use App\Models\Obligation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RegenerateInstalments
{
    public function __construct(private readonly GenerateInstalmentSchedule $generateInstalmentSchedule) {}

    /** @return array<int, Instalment> */
    public function handle(User $user, Obligation $obligation, GenerateInstalmentScheduleData $data): array
    {
        $obligation->loadMissing('record.profile');
        Gate::forUser($user)->authorize('update', $obligation->record);

        return DB::transaction(function () use ($user, $obligation, $data): array {
            $locked = Obligation::query()->whereKey($obligation->getKey())->lockForUpdate()->firstOrFail();
            $locked->loadMissing('record.profile');
            Gate::forUser($user)->authorize('update', $locked->record);
            $hasPaidInstalment = $locked->instalments()->where('paid_minor', '>', 0)->exists();
            $hasAllocation = $locked->instalments()->whereHas('allocations')->exists();

            if ($hasPaidInstalment || $hasAllocation) {
                throw ValidationException::withMessages(['schedule' => 'A schedule cannot be regenerated after a payment has been allocated.']);
            }

            $locked->instalments()->delete();

            return $this->generateInstalmentSchedule->handle($user, $locked, $data);
        });
    }
}
