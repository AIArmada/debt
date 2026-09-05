<?php

namespace App\Services;

use App\Domain\Money\Decimal;
use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;

class CommunicationTemplate
{
    /** @return array{subject: string, body: string} */
    public function render(Obligation $obligation, string $template): array
    {
        $party = $obligation->loadMissing('record.partyLinks.party')->record->primaryParty();
        $name = (string) ($party?->preferred_name ?: 'there');
        $balance = $obligation->current_total_balance === null
            ? 'the current balance'
            : MoneyAmount::format($obligation->currentPositionAmount(), (string) $obligation->currency);

        if ($obligation->isPositionReversed()) {
            $balance .= ' (they owe you)';
        }

        if ($obligation->obligation_kind === 'asset') {
            $outstanding = Decimal::display($obligation->current_subject_quantity).' '.($obligation->subject_unit ?: 'item');

            return match ($template) {
                'return_reminder' => ['subject' => 'Asset return reminder', 'body' => "Hi {$name},\n\nThis is a friendly reminder about returning {$obligation->subject_name} ({$outstanding}) recorded under {$obligation->title}. Please let me know when it will be returned or if the condition has changed.\n\nThank you."],
                'settlement_follow_up' => ['subject' => 'Asset return follow-up', 'body' => "Hi {$name},\n\nI would like to follow up about settling the asset obligation {$obligation->title}. Please let me know the latest position and a realistic next step.\n\nThank you."],
                default => ['subject' => 'Asset update request', 'body' => "Hi {$name},\n\nCould you please confirm the latest position for {$obligation->subject_name} under {$obligation->title}? I am reviewing the return details and want to keep the record accurate.\n\nThank you."],
            };
        }

        if ($obligation->obligation_kind === 'service') {
            return match ($template) {
                'service_reminder' => ['subject' => 'Service reminder', 'body' => "Hi {$name},\n\nThis is a friendly reminder about the service or time recorded as {$obligation->title}. Please let me know the latest progress and expected completion date.\n\nThank you."],
                'settlement_follow_up' => ['subject' => 'Service follow-up', 'body' => "Hi {$name},\n\nI would like to follow up about completing {$obligation->title}. Please let me know the latest position and a realistic next step.\n\nThank you."],
                default => ['subject' => 'Service update request', 'body' => "Hi {$name},\n\nCould you please send me an update on the service or time owed under {$obligation->title}? I am reviewing the record and want to keep it accurate.\n\nThank you."],
            };
        }

        if ($obligation->obligation_kind === 'action') {
            return match ($template) {
                'commitment_reminder' => ['subject' => 'Commitment reminder', 'body' => "Hi {$name},\n\nThis is a friendly reminder about the commitment recorded as {$obligation->title}. Please let me know the latest progress and expected completion date.\n\nThank you."],
                'settlement_follow_up' => ['subject' => 'Commitment follow-up', 'body' => "Hi {$name},\n\nI would like to follow up about completing {$obligation->title}. Please let me know the latest position and a realistic next step.\n\nThank you."],
                default => ['subject' => 'Commitment update request', 'body' => "Hi {$name},\n\nCould you please send me an update on {$obligation->title}? I am reviewing the agreed outcome and want to keep the record accurate.\n\nThank you."],
            };
        }

        return match ($template) {
            'payment_submitted' => ['subject' => 'Payment submitted', 'body' => "Hi {$name},\n\nI have submitted a payment of {$balance} towards {$obligation->title}. I will share the confirmation once it is available.\n\nThank you."],
            'payment_planned' => ['subject' => 'Payment planned', 'body' => "Hi {$name},\n\nI am planning a payment towards {$obligation->title}. Please let me know if the payment instructions or balance have changed.\n\nThank you."],
            'request_balance_confirmation' => ['subject' => 'Balance confirmation request', 'body' => "Hi {$name},\n\nCould you please confirm the current balance for {$obligation->title}? I am reviewing my records and want to make sure they are accurate.\n\nThank you."],
            'request_payment_instructions' => ['subject' => 'Payment instructions request', 'body' => "Hi {$name},\n\nCould you please confirm the payment instructions and reference for {$obligation->title}? I will review them before making a payment.\n\nThank you."],
            'collection_reminder' => ['subject' => 'Friendly balance reminder', 'body' => "Hi {$name},\n\nThis is a friendly reminder about the {$obligation->title} balance of {$balance}. Please let me know when you expect to settle it, or if you would like to discuss a plan.\n\nThank you."],
            default => ['subject' => 'Settlement follow-up', 'body' => "Hi {$name},\n\nI would like to follow up about settling {$obligation->title}. Please let me know the latest position and a realistic next step.\n\nThank you."],
        };
    }
}
