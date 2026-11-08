<?php

namespace App\Features\PromiseTracker\EntryPoints\Livewire\Imports;

use App\Features\PromiseTracker\Actions\Promises\ConfirmImportRow;
use App\Features\PromiseTracker\Actions\Promises\Data\ImportCsvData;
use App\Features\PromiseTracker\Actions\Promises\Data\ImportProfileExportData;
use App\Features\PromiseTracker\Actions\Promises\DismissImportRow;
use App\Features\PromiseTracker\Actions\Promises\ImportCsv;
use App\Features\PromiseTracker\Actions\Promises\ImportProfileExport;
use App\Features\PromiseTracker\Domain\Queries\RecordVisibilityQuery;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\ImportRow;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class Index extends Component
{
    use WithFileUploads;

    public FinancialProfile $profile;

    public ?TemporaryUploadedFile $file = null;

    public ?TemporaryUploadedFile $restoreFile = null;

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('view', $profile);
    }

    public function upload(ImportCsv $importCsv): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $importCsv->handle($user, $this->profile, ImportCsvData::fromInput(['file' => $this->file]));
        $this->reset('file');
    }

    public function restore(ImportProfileExport $importProfileExport): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $profile = $importProfileExport->handle($user, ImportProfileExportData::fromInput(['file' => $this->restoreFile]));
        $this->reset('restoreFile');
        $this->redirectRoute('promises.index', ['profile' => $profile], navigate: true);
    }

    public function confirm(string $rowId, ConfirmImportRow $confirmImportRow): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $row = $this->row($rowId);
        $confirmImportRow->handle($user, $row);
    }

    public function dismiss(string $rowId, DismissImportRow $dismissImportRow): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $dismissImportRow->handle($user, $this->row($rowId));
    }

    public function render(RecordVisibilityQuery $recordVisibilityQuery): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $visibleRecordIds = $recordVisibilityQuery
            ->forUser(Record::query(), $user)
            ->select('records.id');

        return view('livewire.imports.index', [
            'batches' => $this->profile->importBatches()
                ->with([
                    'rows.suggestedObligation' => function (Relation $obligations) use ($visibleRecordIds): void {
                        $obligations->whereIn('record_id', $visibleRecordIds);
                    },
                ])
                ->latest()
                ->get(),
        ])->layout('layouts.app', ['title' => 'Imports']);
    }

    private function row(string $rowId): ImportRow
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $visibleRecordIds = app(RecordVisibilityQuery::class)
            ->forUser(Record::query(), $user)
            ->select('records.id');

        return ImportRow::query()
            ->whereKey($rowId)
            ->whereHas('batch', fn (Builder $query): Builder => $query->where('profile_id', $this->profile->getKey()))
            ->whereHas(
                'suggestedObligation',
                function (Builder $obligations) use ($visibleRecordIds): void {
                    $obligations->whereIn('record_id', $visibleRecordIds);
                },
            )
            ->firstOrFail();
    }
}
