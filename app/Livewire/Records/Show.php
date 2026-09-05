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
            'profile:id,owner_user_id,name,base_currency,is_archived',
            'partyLinks' => fn ($query) => $query->select(['id', 'record_id', 'party_id', 'role', 'is_primary', 'notes']),
            'partyLinks.party' => fn ($query) => $query->select(['id', 'profile_id', 'preferred_name']),
            'partyLinks.party.contacts' => fn ($query) => $query->select(['id', 'party_id', 'value', 'is_message_safe']),
            'documents' => fn ($query) => $query->select([
                'documents.id', 'documents.profile_id', 'documents.evidence_type', 'documents.title', 'documents.source',
                'documents.external_url', 'documents.content', 'documents.captured_on', 'documents.category',
                'documents.verification_status', 'documents.ocr_status', 'documents.extracted_text',
            ]),
            'documents.media' => fn ($query) => $query->select([
                'id', 'model_id', 'model_type', 'collection_name', 'file_name', 'mime_type', 'size', 'disk',
                'custom_properties', 'order_column',
            ]),
            'obligations' => fn ($query) => $query->select([
                'id', 'record_id', 'direction', 'category', 'title', 'description', 'status', 'tracking_mode',
                'obligation_kind', 'currency', 'current_total_balance', 'currency_balances', 'minimum_payment_amount',
                'subject_name', 'current_subject_quantity', 'subject_unit', 'quantity_mode', 'completion_criteria',
                'due_on', 'next_due_on', 'created_at',
            ]),
            'obligations.record' => fn ($query) => $query->select(['id', 'profile_id', 'title', 'sensitivity', 'is_archived']),
            'obligations.record.profile' => fn ($query) => $query->select(['id', 'owner_user_id', 'name', 'base_currency', 'is_archived']),
            'obligations.transactions' => fn ($query) => $query->select([
                'id', 'obligation_id', 'entry_type', 'balance_effect', 'status', 'amount', 'currency', 'occurred_on',
                'external_reference', 'note',
            ]),
            'obligations.transactions.documents' => fn ($query) => $query->select([
                'documents.id', 'documents.profile_id', 'documents.evidence_type', 'documents.title', 'documents.source',
                'documents.external_url', 'documents.content', 'documents.captured_on', 'documents.category',
                'documents.verification_status', 'documents.ocr_status', 'documents.extracted_text',
            ]),
            'obligations.transactions.documents.media' => fn ($query) => $query->select([
                'id', 'model_id', 'model_type', 'collection_name', 'file_name', 'mime_type', 'size', 'disk',
                'custom_properties', 'order_column',
            ]),
            'obligations.partyLinks' => fn ($query) => $query->select(['id', 'obligation_id', 'party_id', 'role', 'share_basis', 'share_percent']),
            'obligations.partyLinks.party' => fn ($query) => $query->select(['id', 'preferred_name']),
            'obligations.events' => fn ($query) => $query->select(['id', 'obligation_id', 'event_type', 'quantity', 'unit', 'occurred_on', 'note']),
            'obligations.events.documents' => fn ($query) => $query->select([
                'documents.id', 'documents.profile_id', 'documents.evidence_type', 'documents.title', 'documents.source',
                'documents.external_url', 'documents.content', 'documents.captured_on', 'documents.category',
                'documents.verification_status', 'documents.ocr_status', 'documents.extracted_text',
            ]),
            'obligations.events.documents.media' => fn ($query) => $query->select([
                'id', 'model_id', 'model_type', 'collection_name', 'file_name', 'mime_type', 'size', 'disk',
                'custom_properties', 'order_column',
            ]),
            'obligations.terms' => fn ($query) => $query->select([
                'id', 'obligation_id', 'version', 'calculation_method', 'interest_rate', 'interest_period',
                'compounding_period', 'late_fee_amount', 'late_fee_rate', 'storage_fee_amount', 'storage_fee_period',
                'fixed_installment_amount', 'formula', 'effective_from',
            ]),
        ]);

        $calculationPreviews = $record->obligations
            ->filter(fn ($obligation): bool => $obligation->obligation_kind === 'money')
            ->mapWithKeys(fn ($obligation): array => [$obligation->id => app(ObligationCalculator::class)->preview($obligation, $obligation->terms->first())]);

        return view('livewire.records.show', compact('record', 'calculationPreviews'))
            ->layout('layouts.app', ['title' => $record->title]);
    }
}
