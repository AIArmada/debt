<?php

namespace App\Livewire\Records;

use App\Domain\Calculations\ObligationCalculator;
use App\Models\Record;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    public Record $record;

    public function mount(Record $record): void
    {
        Gate::authorize('view', $record);
        $this->record = $record;
    }

    #[On('obligation-added')]
    #[On('transaction-recorded')]
    #[On('transaction-updated')]
    #[On('event-recorded')]
    #[On('document-uploaded')]
    #[On('terms-updated')]
    #[On('party-added')]
    #[On('party-removed')]
    public function refreshRecord(): void
    {
        Gate::authorize('view', $this->record);
        $this->record->refresh();
    }

    public function render(): View
    {
        Gate::authorize('view', $this->record);

        $record = $this->record->load([
            'profile',
            'partyLinks.party.contacts',
            'partyLinks.party.addresses',
            'documents',
            'obligations.transactions.documents',
            'obligations.transactions.partyLinks.party',
            'obligations.partyLinks.party',
            'obligations.events.documents',
            'obligations.events.createdBy',
            'obligations.terms',
            'obligations.calculationScenarios',
            'obligations.paymentSchedules',
            'obligations.collectionSchedules',
            'obligations.deliveryInstructions.address',
            'obligations.pledgedAssets',
        ]);

        $calculationPreviews = $record->obligations
            ->filter(fn ($obligation): bool => $obligation->obligation_kind === 'money')
            ->mapWithKeys(fn ($obligation): array => [$obligation->id => app(ObligationCalculator::class)->preview($obligation, $obligation->terms->first())]);

        return view('livewire.records.show', compact('record', 'calculationPreviews'))
            ->layout('layouts.app', ['title' => $record->title]);
    }
}
