<?php

namespace App\Actions\Promises;

use App\Actions\Promises\Data\AttachEvidenceData;
use App\Domain\Attachments\AttachmentParent;
use App\Models\Attachment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AttachEvidence
{
    public function __construct(
        private readonly AttachmentParent $attachmentParent,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function handle(User $user, Model $parent, AttachEvidenceData $data): Attachment
    {
        Gate::forUser($user)->authorize('create', [Attachment::class, $parent]);
        $profile = $this->attachmentParent->profile($parent);
        $storedPath = null;
        $disk = Storage::disk('private');

        try {
            return DB::transaction(function () use ($user, $parent, $data, $profile, $disk, &$storedPath): Attachment {
                $originalName = $data->file?->getClientOriginalName() ?? (parse_url((string) $data->linkUrl, PHP_URL_HOST) ?: 'Evidence link');
                $mime = $data->file?->getMimeType();
                $size = $data->file?->getSize();
                $diskPath = null;

                if ($data->file !== null) {
                    $directory = 'attachments/'.$profile->getKey().'/'.Str::uuid();
                    $storedName = Str::uuid().'-'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
                    $extension = $data->file->guessExtension();
                    $storedPath = $directory.'/'.$storedName.($extension ? '.'.$extension : '');
                    if (! $disk->putFileAs($directory, $data->file, basename($storedPath))) {
                        throw new RuntimeException('Evidence file could not be stored.');
                    }
                    $diskPath = $storedPath;
                }

                $attachment = $this->attachmentParent->attachments($parent)->create([
                    'profile_id' => $profile->getKey(),
                    'attachable_type' => $parent->getMorphClass(),
                    'attachable_id' => $parent->getKey(),
                    'disk_path' => $diskPath,
                    'link_url' => $data->linkUrl,
                    'original_name' => Str::limit(basename($originalName), 255, ''),
                    'mime' => $mime,
                    'size_bytes' => $size,
                    'category' => $data->category,
                    'recorded_by' => $user->getKey(),
                    'created_at' => now(),
                ]);

                $this->activityLogger->record(
                    $profile,
                    $user,
                    $attachment,
                    'evidence_attached',
                    after: [
                        'attachment_id' => $attachment->getKey(),
                        'category' => $data->category->value,
                        'parent_type' => $parent->getMorphClass(),
                        'parent_id' => $parent->getKey(),
                    ],
                );

                return $attachment;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $disk->delete($storedPath);
            }

            throw $exception;
        }
    }
}
