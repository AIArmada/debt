<?php

namespace App\Http\Controllers;

use App\Domain\Queries\ProfileExport;
use App\Domain\Queries\RecordVisibilityQuery;
use App\Jobs\GenerateProfileExport;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class ProfileExportController extends Controller
{
    public function csv(FinancialProfile $profile, ProfileExport $export): Response
    {
        Gate::authorize('view', $profile);
        $user = request()->user();
        abort_unless($user instanceof User, 401);

        if ($export->rowCount($profile, $user) > ProfileExport::ROW_CAP) {
            return $this->queue($profile, $user, 'csv');
        }

        return response($export->toCsv($profile, $user), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="promises.csv"',
        ]);
    }

    public function json(FinancialProfile $profile, ProfileExport $export): Response
    {
        Gate::authorize('view', $profile);
        $user = request()->user();
        abort_unless($user instanceof User, 401);

        if ($export->rowCount($profile, $user) > ProfileExport::ROW_CAP) {
            return $this->queue($profile, $user, 'json');
        }

        return response($export->toJson($profile, $user), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="promises.json"',
        ]);
    }

    public function zip(FinancialProfile $profile, ProfileExport $export): BinaryFileResponse
    {
        Gate::authorize('view', $profile);
        $user = request()->user() ?? Auth::user();
        abort_unless($user instanceof User, 401);

        $path = tempnam(sys_get_temp_dir(), 'promise-profile-export-');
        if ($path === false) {
            throw new RuntimeException('A temporary export file could not be created.');
        }

        $archive = new ZipArchive;
        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('The ZIP export could not be opened.');
        }

        try {
            if (! $archive->addFromString('export.json', $export->toJson($profile, $user))) {
                throw new RuntimeException('The export payload could not be added to the ZIP.');
            }
            $disk = Storage::disk('private');
            foreach ($export->attachmentFiles($profile, $user) as $attachment) {
                if ($attachment->link_url !== null) {
                    continue;
                }
                if ($attachment->disk_path === null || ! $disk->exists($attachment->disk_path)) {
                    throw new RuntimeException('A stored attachment is missing from private storage.');
                }

                $entryName = 'files/'.$attachment->getKey().'/'.basename($attachment->original_name);
                if (! $archive->addFile($disk->path($attachment->disk_path), $entryName)) {
                    throw new RuntimeException('An attachment could not be added to the ZIP.');
                }
            }
            if (! $archive->close()) {
                throw new RuntimeException('The ZIP export could not be finalized.');
            }
        } catch (Throwable $exception) {
            try {
                $archive->close();
            } catch (Throwable) {
            }
            @unlink($path);
            throw $exception;
        }

        return response()->download($path, 'promise-profile.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function download(FinancialProfile $profile, string $format, string $export, RecordVisibilityQuery $recordVisibilityQuery): mixed
    {
        Gate::authorize('view', $profile);
        abort_unless(in_array($format, ['csv', 'json'], true), 404);
        $user = request()->user();
        abort_unless($user instanceof User, 401);
        abort_unless(hash_equals((string) $user->getKey(), (string) request()->query('user_id', '')), 404);

        $path = GenerateProfileExport::path($profile->getKey(), $export, $format);
        abort_unless(Storage::disk('private')->exists($path), 404);
        $recordIdsPath = GenerateProfileExport::recordIdsPath($profile->getKey(), $export);
        abort_unless(Storage::disk('private')->exists($recordIdsPath), 404);
        try {
            $recordIds = json_decode(Storage::disk('private')->get($recordIdsPath), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(404);
        }
        abort_unless(is_array($recordIds) && array_is_list($recordIds), 404);
        if ($recordIds !== []) {
            abort_unless($recordVisibilityQuery->forUser($profile->records()->getQuery(), $user)
                ->whereIn('records.id', $recordIds)
                ->count() === count($recordIds), 404);
        }

        return Storage::disk('private')->download($path, "promises.{$format}");
    }

    private function queue(FinancialProfile $profile, User $user, string $format): Response
    {
        $exportId = (string) Str::uuid();
        GenerateProfileExport::dispatch($profile->getKey(), $user->getKey(), $format, $exportId);

        return response([
            'status' => 'queued',
            'message' => 'This export is being prepared. Use the download link when it is ready.',
            'download_url' => URL::temporarySignedRoute('profile.exports.download', now()->addDay(), [
                'profile' => $profile,
                'format' => $format,
                'export' => $exportId,
                'user_id' => $user->getKey(),
            ]),
        ], 202);
    }
}
