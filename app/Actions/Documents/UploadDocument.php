<?php

namespace App\Actions\Documents;

use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Models\ObligationEvent;
use App\Models\User;
use App\Rules\SafeUpload;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadDocument
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ActivityNotifier $activityNotifier,
    ) {}

    public function handle(
        User $user,
        Obligation $obligation,
        ?UploadedFile $file,
        string $category,
        string $verificationStatus,
        ?FinancialTransaction $transaction = null,
        ?ObligationEvent $event = null,
        string $evidenceType = 'file',
        ?string $title = null,
        ?string $source = null,
        ?string $content = null,
        ?string $externalUrl = null,
        ?string $capturedOn = null,
    ): Document {
        Gate::forUser($user)->authorize('uploadDocument', $obligation);

        if (! in_array($evidenceType, ['file', 'link', 'note'], true)) {
            throw ValidationException::withMessages(['evidence_type' => 'Choose a valid evidence form.']);
        }

        if ($evidenceType === 'file' && $file === null) {
            throw ValidationException::withMessages(['file' => 'Choose a file to upload.']);
        }

        if ($evidenceType === 'file') {
            Validator::make(
                ['file' => $file],
                ['file' => ['required', 'file', 'max:51200', new SafeUpload]],
            )->validate();
        }

        $externalUrlIsSafe = is_string($externalUrl)
            && filter_var($externalUrl, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($externalUrl, PHP_URL_SCHEME)), ['http', 'https'], true);

        if ($evidenceType === 'link' && ! $externalUrlIsSafe) {
            throw ValidationException::withMessages(['external_url' => 'Enter a valid source link.']);
        }

        if ($evidenceType === 'note' && blank($content)) {
            throw ValidationException::withMessages(['content' => 'Write the evidence note before saving.']);
        }

        if ($transaction !== null && $transaction->obligation_id !== $obligation->getKey()) {
            abort(404);
        }

        if ($event !== null && $event->obligation_id !== $obligation->getKey()) {
            abort(404);
        }

        if ($transaction !== null && $event !== null) {
            throw new \InvalidArgumentException('A document can support a transaction or an obligation event, not both.');
        }

        $obligation->loadMissing('record.profile');
        $profile = $obligation->record->profile;

        try {
            $document = DB::transaction(function () use ($user, $obligation, $profile, $file, $category, $verificationStatus, $transaction, $event, $evidenceType, $title, $source, $content, $externalUrl, $capturedOn): Document {
                $document = Document::create([
                    'profile_id' => $profile->getKey(),
                    'uploaded_by_user_id' => $user->getKey(),
                    'status' => 'active',
                    'evidence_type' => $evidenceType,
                    'title' => $title ?: ($evidenceType === 'file' ? SafeUpload::sanitizedFilename($file) : null),
                    'source' => $source,
                    'external_url' => $externalUrl,
                    'content' => $content,
                    'captured_on' => $capturedOn,
                    'category' => $category,
                    'verification_status' => $verificationStatus,
                ]);

                if ($evidenceType === 'file') {
                    $originalFilename = SafeUpload::sanitizedFilename($file);
                    $document->addMedia($file)
                        ->usingName(pathinfo($originalFilename, PATHINFO_FILENAME))
                        ->usingFileName($originalFilename)
                        ->withCustomProperties([
                            'original_filename' => $originalFilename,
                            'checksum' => hash_file('sha256', $file->getRealPath()),
                        ])
                        ->toMediaCollection(Document::MEDIA_COLLECTION);
                }

                DocumentLink::create([
                    'document_id' => $document->getKey(),
                    'record_id' => $transaction === null && $event === null ? $obligation->record_id : null,
                    'obligation_id' => null,
                    'financial_transaction_id' => $transaction?->getKey(),
                    'obligation_event_id' => $event?->getKey(),
                    'purpose' => $transaction !== null ? 'transaction_evidence' : ($event !== null ? 'event_evidence' : 'evidence'),
                ]);

                $this->auditLogger->record(
                    $profile,
                    $user,
                    Document::class,
                    $document->getKey(),
                    'uploaded',
                    after: [
                        'profile_id' => $document->profile_id,
                        'uploaded_by_user_id' => $document->uploaded_by_user_id,
                        'evidence_type' => $document->evidence_type,
                        'title' => $document->title,
                        'original_filename' => $document->original_filename,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'category' => $document->category,
                        'verification_status' => $document->verification_status,
                    ],
                );

                return $document;
            });

            $this->activityNotifier->notifyObligation(
                $obligation,
                'evidence_added',
                'New evidence was added',
                'New evidence was attached to one of your private records.',
                'normal',
                [
                    'evidence_type' => $evidenceType,
                    'category' => $category,
                    'target' => $transaction !== null ? 'movement' : ($event !== null ? 'fulfillment_event' : 'obligation'),
                ],
            );

            if ($evidenceType !== 'file') {
                return $document;
            }

            try {
                return app(ProcessDocumentOcr::class)->handle($document);
            } catch (Throwable $exception) {
                report($exception);
                $document->update([
                    'ocr_status' => 'failed',
                    'ocr_provider' => 'local',
                    'ocr_error' => 'Document text extraction failed.',
                ]);

                return $document->fresh();
            }
        } catch (Throwable $exception) {
            throw $exception;
        }
    }
}
