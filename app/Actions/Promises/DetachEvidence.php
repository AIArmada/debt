<?php

namespace App\Actions\Promises;

use App\Models\Attachment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class DetachEvidence
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function handle(User $user, Attachment $attachment): void
    {
        $attachment->loadMissing('attachable');
        Gate::forUser($user)->authorize('delete', $attachment);

        DB::transaction(function () use ($user, $attachment): void {
            if ($attachment->disk_path !== null) {
                Storage::disk('private')->delete($attachment->disk_path);
            }

            $this->activityLogger->record(
                $attachment->profile,
                $user,
                $attachment,
                'evidence_detached',
                before: ['attachment_id' => $attachment->getKey()],
            );
            $attachment->delete();
        });
    }
}
