<?php

namespace App\Livewire\Communication;

use App\Actions\Communication\DraftCommunicationMessage;
use App\Actions\Communication\SendCommunicationMessage;
use App\Models\CommunicationMessage;
use App\Models\Obligation;
use App\Services\CommunicationTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Composer extends Component
{
    public Obligation $obligation;

    #[Validate('required|in:whatsapp,email')]
    public string $channel = 'whatsapp';

    #[Validate('required|in:payment_submitted,payment_planned,request_balance_confirmation,request_payment_instructions,collection_reminder,return_reminder,service_reminder,commitment_reminder,request_update,settlement_follow_up')]
    public string $template = 'request_balance_confirmation';

    #[Validate('nullable|string|max:255')]
    public string $recipient = '';

    #[Validate('required|string|max:10000')]
    public string $body = '';

    public ?string $whatsappUrl = null;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('manageCommunication', $obligation);
        $this->obligation = $obligation->load('record.partyLinks.party.contacts');
        $this->template = $obligation->obligation_kind === 'asset'
            ? 'return_reminder'
            : ($obligation->obligation_kind === 'service' ? 'service_reminder' : ($obligation->obligation_kind === 'action' ? 'commitment_reminder' : 'request_balance_confirmation'));
        $emailContact = $obligation->record->primaryParty()?->contacts
            ->where('type', 'email')
            ->where('is_message_safe', true)
            ->first();
        $this->recipient = $emailContact === null ? '' : (string) $emailContact->value;
        $this->compose();
    }

    public function updatedTemplate(CommunicationTemplate $communicationTemplate): void
    {
        $this->compose($communicationTemplate);
    }

    public function compose(?CommunicationTemplate $communicationTemplate = null): void
    {
        $communicationTemplate ??= app(CommunicationTemplate::class);
        $rendered = $communicationTemplate->render($this->obligation, $this->template);
        $this->body = $rendered['body'];
        $this->whatsappUrl = null;
    }

    public function saveDraft(DraftCommunicationMessage $draftCommunicationMessage): void
    {
        Gate::authorize('manageCommunication', $this->obligation);
        $this->validate();
        $draftCommunicationMessage->handle(auth()->user(), $this->obligation, $this->channel, $this->recipient, $this->body);
        $this->whatsappUrl = $this->channel === 'whatsapp' ? 'https://wa.me/?text='.urlencode($this->body) : null;
        session()->flash('communication-saved', 'The message was saved as a draft.');
    }

    public function sendEmail(SendCommunicationMessage $sendCommunicationMessage, CommunicationTemplate $communicationTemplate): void
    {
        Gate::authorize('manageCommunication', $this->obligation);
        $this->channel = 'email';
        $this->validate([
            'recipient' => 'required|email|max:255',
            'body' => 'required|string|max:10000',
        ]);
        $message = app(DraftCommunicationMessage::class)->handle(auth()->user(), $this->obligation, 'email', $this->recipient, $this->body);
        $subject = $communicationTemplate->render($this->obligation, $this->template)['subject'];
        $sendCommunicationMessage->handle(auth()->user(), $message, $subject);
        session()->flash('communication-sent', 'The email was sent and recorded.');
    }

    public function render(): View
    {
        Gate::authorize('manageCommunication', $this->obligation);

        return view('livewire.communication.composer', [
            'messages' => CommunicationMessage::query()
                ->select(['id', 'communication_thread_id', 'status', 'body', 'created_at'])
                ->with(['thread' => fn ($query) => $query->select(['id', 'obligation_id', 'channel'])])
                ->whereHas('thread', fn ($query) => $query->where('obligation_id', $this->obligation->getKey()))
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
