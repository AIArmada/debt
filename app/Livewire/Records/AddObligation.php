<?php

namespace App\Livewire\Records;

use App\Actions\Obligations\CreateObligation;
use App\Actions\Records\CreateRecord;
use App\Models\Record;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AddObligation extends Create
{
    public Record $record;

    public function mount(?Record $record = null): void
    {
        abort_if($record === null, 404);
        Gate::authorize('update', $record);
        $this->record = $record->load('profile');
        $this->profileId = $record->profile_id;
        $this->currency = $record->profile->base_currency;
        $this->estimatedValueCurrency = $record->profile->base_currency;
    }

    public function save(CreateRecord $createRecord): void
    {
        try {
            $validated = array_merge($this->obligationState(), $this->validate($this->obligationRules()));
            $this->validateObligationRequirements($validated);
            app(CreateObligation::class)->handle(auth()->user(), $this->record, $this->obligationData($validated));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->resetExcept('record');
        $this->profileId = $this->record->profile_id;
        $this->currency = $this->record->profile->base_currency;
        $this->estimatedValueCurrency = $this->record->profile->base_currency;
        $this->dispatch('obligation-added');
        $this->dispatch('modal-close', name: 'add-obligation');
    }

    public function render(): View
    {
        Gate::authorize('update', $this->record);

        return view('livewire.records.add-obligation');
    }
}
