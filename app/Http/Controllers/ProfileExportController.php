<?php

namespace App\Http\Controllers;

use App\Domain\Queries\ProfileExport;
use App\Jobs\GenerateProfileExport;
use App\Models\FinancialProfile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ProfileExportController extends Controller
{
    public function csv(FinancialProfile $profile, ProfileExport $export): Response
    {
        Gate::authorize('view', $profile);

        if ($export->rowCount($profile) > ProfileExport::ROW_CAP) {
            return $this->queue($profile, 'csv');
        }

        return response($export->toCsv($profile), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="promises.csv"',
        ]);
    }

    public function json(FinancialProfile $profile, ProfileExport $export): Response
    {
        Gate::authorize('view', $profile);

        if ($export->rowCount($profile) > ProfileExport::ROW_CAP) {
            return $this->queue($profile, 'json');
        }

        return response($export->toJson($profile), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="promises.json"',
        ]);
    }

    public function download(FinancialProfile $profile, string $format, string $export): mixed
    {
        Gate::authorize('view', $profile);
        abort_unless(in_array($format, ['csv', 'json'], true), 404);

        $path = GenerateProfileExport::path($profile->getKey(), $export, $format);
        abort_unless(Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->download($path, "promises.{$format}");
    }

    private function queue(FinancialProfile $profile, string $format): Response
    {
        $exportId = (string) Str::uuid();
        GenerateProfileExport::dispatch($profile->getKey(), $format, $exportId);

        return response([
            'status' => 'queued',
            'message' => 'This export is being prepared. Use the download link when it is ready.',
            'download_url' => URL::temporarySignedRoute('profile.exports.download', now()->addDay(), [
                'profile' => $profile,
                'format' => $format,
                'export' => $exportId,
            ]),
        ], 202);
    }
}
