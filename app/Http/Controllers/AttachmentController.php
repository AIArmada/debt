<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\FinancialProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentController extends Controller
{
    public function download(FinancialProfile $profile, Attachment $attachment): RedirectResponse|StreamedResponse
    {
        abort_unless($attachment->profile->is($profile), 404);
        Gate::authorize('view', $attachment);

        if ($attachment->link_url !== null) {
            return redirect()->away($attachment->link_url);
        }

        abort_unless($attachment->disk_path !== null && Storage::disk('private')->exists($attachment->disk_path), 404);

        return Storage::disk('private')->download(
            $attachment->disk_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime ?? 'application/octet-stream'],
        );
    }
}
