<?php

namespace App\Livewire\Imports;

use App\Actions\Imports\ImportBankStatement;
use App\Actions\Obligations\RecordTransaction;
use App\Livewire\Concerns\InteractsWithAccessibleProfiles;
use App\Models\BankImportRow;
use App\Models\Obligation;
use App\Rules\SafeUpload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Index extends Component
{
    use InteractsWithAccessibleProfiles;
    use WithFileUploads;

    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public ?TemporaryUploadedFile $file = null;

    #[Validate('required|alpha|size:3')]
    public string $currency = 'MYR';

    public function mount(): void
    {
        $profiles = $this->accessibleProfilesCollection();
        $selectedProfileId = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $profiles->contains('id', $selectedProfileId)
            ? $selectedProfileId
            : $profiles->first()?->getKey();
        if ($this->profileId !== null) {
            $this->currency = (string) $profiles->firstWhere('id', $this->profileId)->base_currency;
        }
    }

    public function updatedProfileId(): void
    {
        $profile = $this->accessibleProfile($this->profileId);
        $this->currency = (string) $profile->base_currency;
        session()->put('selected_profile_id', $this->profileId);
    }

    public function runImport(ImportBankStatement $importBankStatement): void
    {
        $validated = $this->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240', new SafeUpload], 'currency' => 'required|alpha|size:3']);
        $profile = $this->accessibleProfile($this->profileId);
        $importBankStatement->handle(auth()->user(), $profile, $this->file, strtoupper($validated['currency']));
        $this->reset('file');
        session()->flash('import-created', 'The statement was imported. Review each row before recording it as a payment or collection.');
    }

    public function matchRow(string $rowId, string $obligationId): void
    {
        $row = $this->row($rowId);
        Gate::authorize('manageImports', $row->bankImport->profile);

        if ($row->financial_transaction_id !== null) {
            $this->addError('file', 'A recorded bank row cannot be unmatched. Edit the linked movement instead.');

            return;
        }

        if (blank($obligationId)) {
            $row->update(['obligation_id' => null, 'status' => 'unmatched']);
            session()->flash('import-row-unmatched', 'The bank-row match was cleared.');

            return;
        }

        $obligation = Obligation::query()->whereKey($obligationId)->where('obligation_kind', 'money')->whereHas('record', function ($query) use ($row): void {
            $query->where('profile_id', $row->bankImport->profile_id)->where('is_archived', false);
        })->firstOrFail();
        $row->update(['obligation_id' => $obligation->getKey(), 'status' => 'matched']);
    }

    public function recordRow(string $rowId, RecordTransaction $recordTransaction): void
    {
        $row = $this->row($rowId);
        Gate::authorize('manageImports', $row->bankImport->profile);
        $obligation = $row->obligation;
        if ($obligation === null) {
            $this->addError('file', 'Select a record for this row first.');

            return;
        }
        try {
            if ($row->financial_transaction_id !== null) {
                $this->addError('file', 'This bank row is already linked to a recorded movement.');

                return;
            }

            $transaction = $recordTransaction->handle($obligation, [
                'status' => 'confirmed',
                'amount' => '0',
                'amount_minor' => abs((int) $row->amount),
                'currency' => $row->currency,
                'occurred_on' => $row->occurred_on?->toDateString(),
                'external_reference' => $row->external_reference,
                'note' => 'Imported from bank statement'.($row->description ? ': '.$row->description : ''),
                'entry_type' => $row->suggested_direction === 'receivable' ? 'collection' : 'payment',
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->addError('file', $message);
                }
            }

            return;
        }
        $row->update(['status' => 'recorded', 'financial_transaction_id' => $transaction->getKey()]);
        $row->bankImport()->increment('matched_count');
        session()->flash('import-row-recorded', 'The imported transaction was recorded.');
    }

    public function render(): View
    {
        $profiles = $this->accessibleProfilesCollection();
        $profile = $this->accessibleProfile($this->profileId);
        Gate::authorize('view', $profile);
        $canManageImports = in_array($this->accessibleProfileRole($this->profileId), ['owner', 'editor'], true);
        $recordOptions = $profile->records()
            ->select(['id', 'profile_id', 'title'])
            ->where('is_archived', false)
            ->with(['obligations' => fn ($query) => $query
                ->select(['id', 'record_id', 'obligation_kind', 'title'])
                ->where('obligation_kind', 'money')])
            ->latest()
            ->get();

        $imports = $profile->bankImports()
            ->select(['id', 'profile_id', 'format', 'currency', 'status', 'row_count', 'matched_count', 'created_at'])
            ->with([
                'media' => fn ($query) => $query->select([
                    'id', 'model_id', 'model_type', 'collection_name', 'file_name', 'mime_type', 'size',
                    'disk', 'custom_properties', 'order_column',
                ]),
                'rows' => fn ($query) => $query->select([
                    'id', 'bank_import_id', 'obligation_id', 'financial_transaction_id', 'occurred_on',
                    'description', 'amount', 'currency', 'suggested_direction', 'status',
                ])->with([
                    'obligation' => fn ($obligationQuery) => $obligationQuery->select(['id', 'title']),
                ]),
            ])
            ->latest()
            ->limit(5)
            ->get();

        return view('livewire.imports.index', compact('profile', 'canManageImports', 'profiles', 'recordOptions', 'imports'))
            ->layout('layouts.app', ['title' => 'Bank imports']);
    }

    private function row(string $rowId): BankImportRow
    {
        return BankImportRow::query()->with(['bankImport.profile', 'obligation'])->whereKey($rowId)->firstOrFail();
    }
}
