<?php

namespace App\Livewire\Documents;

use App\Actions\Documents\UploadDocument;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Models\ObligationEvent;
use App\Rules\SafeUpload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class Upload extends Component
{
    use WithFileUploads;

    public Obligation $obligation;

    public ?FinancialTransaction $transaction = null;

    public ?ObligationEvent $event = null;

    public ?string $modalName = null;

    public ?string $transactionId = null;

    public ?string $eventId = null;

    public string $targetType = 'obligation';

    public bool $hasPresetTarget = false;

    public ?TemporaryUploadedFile $file = null;

    public string $evidenceType = 'file';

    public string $title = '';

    public string $source = '';

    public string $content = '';

    public string $externalUrl = '';

    public ?string $capturedOn = null;

    public string $category = 'other';

    public string $verificationStatus = 'needs_review';

    public function mount(Obligation $obligation, ?FinancialTransaction $transaction = null, ?ObligationEvent $event = null, ?string $modalName = null): void
    {
        Gate::authorize('uploadDocument', $obligation);
        $this->obligation = $obligation;
        $this->transaction = $transaction;
        $this->event = $event;
        $this->modalName = $modalName;

        if ($transaction !== null) {
            if ($transaction->obligation_id !== $obligation->getKey()) {
                abort(404);
            }

            $this->targetType = 'transaction';
            $this->transactionId = $transaction->getKey();
            $this->hasPresetTarget = true;
        } elseif ($event !== null) {
            if ($event->obligation_id !== $obligation->getKey()) {
                abort(404);
            }

            $this->targetType = 'event';
            $this->eventId = $event->getKey();
            $this->hasPresetTarget = true;
        }
    }

    #[On('transaction-recorded')]
    #[On('event-recorded')]
    public function refreshTargets(): void
    {
        Gate::authorize('uploadDocument', $this->obligation);
        $this->obligation->refresh();
    }

    public function updatedTargetType(string $targetType): void
    {
        if ($this->hasPresetTarget) {
            return;
        }

        if ($targetType !== 'transaction') {
            $this->transactionId = null;
        }

        if ($targetType !== 'event') {
            $this->eventId = null;
        }
    }

    public function updatedEvidenceType(string $evidenceType): void
    {
        $this->resetValidation('file');

        if ($evidenceType !== 'file') {
            $this->reset('file');
        }
    }

    public function save(UploadDocument $uploadDocument): void
    {
        Gate::authorize('uploadDocument', $this->obligation);

        $this->validate([
            'evidenceType' => 'required|in:file,link,note',
            'title' => $this->evidenceType === 'file' ? 'nullable|string|max:160' : 'required|string|max:160',
            'source' => 'nullable|string|max:160',
            'content' => $this->evidenceType === 'note' ? 'required|string|max:10000' : 'nullable|string|max:10000',
            'externalUrl' => $this->evidenceType === 'link' ? 'required|url|max:2000' : 'nullable|url|max:2000',
            'capturedOn' => 'nullable|date',
            'category' => 'required|in:agreement,receipt,statement,message,photo,audio,video,pawn_ticket,valuation,identity,witness_statement,delivery_proof,other',
            'verificationStatus' => 'required|in:needs_review,verified',
            'file' => $this->evidenceType === 'file'
                ? ['required', 'file', 'max:51200', new SafeUpload]
                : 'nullable',
        ]);

        if (! $this->hasPresetTarget && $this->targetType === 'transaction' && blank($this->transactionId)) {
            $this->addError('transactionId', 'Choose the movement this evidence supports.');

            return;
        }

        if (! $this->hasPresetTarget && $this->targetType === 'event' && blank($this->eventId)) {
            $this->addError('eventId', 'Choose the fulfillment update this evidence supports.');

            return;
        }

        $transaction = $this->targetType !== 'transaction' || blank($this->transactionId)
            ? null
            : $this->obligation->transactions()->whereKey($this->transactionId)->firstOrFail();
        $event = $this->targetType !== 'event' || blank($this->eventId)
            ? null
            : $this->obligation->events()->whereKey($this->eventId)->firstOrFail();

        try {
            $uploadDocument->handle(
                auth()->user(),
                $this->obligation,
                $this->file,
                $this->category,
                $this->verificationStatus,
                $transaction,
                $event,
                $this->evidenceType,
                $this->title ?: null,
                $this->source ?: null,
                $this->content ?: null,
                $this->externalUrl ?: null,
                $this->capturedOn,
            );
        } catch (FileIsTooBig) {
            $this->addError('file', 'This file is larger than the 50 MB upload limit. Choose a smaller file.');

            return;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->reset('file', 'title', 'source', 'content', 'externalUrl', 'capturedOn');
        $this->evidenceType = 'file';
        $this->category = 'other';
        $this->verificationStatus = 'needs_review';

        if (! $this->hasPresetTarget) {
            $this->reset('transactionId', 'eventId');
            $this->targetType = 'obligation';
        }

        $this->dispatch('document-uploaded');
        if ($this->modalName !== null) {
            $this->dispatch('modal-close', name: $this->modalName);
        }
        session()->flash('document-uploaded', 'The evidence was saved privately.');
    }

    public function render(): View
    {
        Gate::authorize('uploadDocument', $this->obligation);

        return view('livewire.documents.upload', [
            'transactions' => $this->obligation->transactions()->latest('occurred_on')->latest('created_at')->get(),
            'events' => $this->obligation->events()->latest('occurred_on')->latest('created_at')->get(),
        ]);
    }
}
