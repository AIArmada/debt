<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Document $document): Response|StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_if($document->evidence_type !== 'file', 404);

        $media = $document->mediaFile();

        abort_if($media === null, 404);

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $media->getDownloadFilename(), [
            'Content-Type' => $media->mime_type ?: 'application/octet-stream',
        ]);
    }
}
