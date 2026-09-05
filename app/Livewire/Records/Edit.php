<?php

namespace App\Livewire\Records;

use App\Actions\Records\UpdateRecord;
use App\Models\Party;
use App\Models\Record;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Edit extends Component
{
    public Record $record;

    public string $title = '';

    public string $description = '';

    public ?string $primaryPartyId = null;

    public string $sensitivity = 'private';

    public function mount(Record $record): void
    {
        Gate::authorize('update', $record);
        $this->record = $record->load('partyLinks.party');
        $this->title = $record->title;
        $this->description = $record->description ?? '';
        $this->primaryPartyId = $record->partyLinks->first(fn ($link): bool => $link->is_primary && $link->role === 'other_party')?->party_id;
        $this->sensitivity = $record->sensitivity;
    }

    public function save(UpdateRecord $updateRecord): void
    {
        $validated = $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'primaryPartyId' => ['nullable', 'uuid', 'exists:parties,id'],
            'sensitivity' => ['required', 'in:private,shared'],
        ]);

        try {
            $updateRecord->handle($this->record, [
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'party_id' => $validated['primaryPartyId'] ?? null,
                'sensitivity' => $validated['sensitivity'],
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        session()->flash('record-updated', 'The shared arrangement was updated.');
        $this->redirectRoute('records.show', $this->record, navigate: true);
    }

    public function render(): View
    {
        Gate::authorize('update', $this->record);

        $parties = Party::query()->where('profile_id', $this->record->profile_id)->whereNull('archived_at')->orderBy('preferred_name')->get();

        return view('livewire.records.edit', compact('parties'))->layout('layouts.app', ['title' => 'Edit '.$this->record->title]);
    }
}
