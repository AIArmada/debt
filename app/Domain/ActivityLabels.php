<?php

namespace App\Domain;

use Illuminate\Support\Str;

final class ActivityLabels
{
    /** @var array<string, string> */
    private const MAP = [
        'promise_created' => 'Promise created',
        'promise_details_updated' => 'Promise details updated',
        'record_note_saved' => 'Note saved',
        'record_archived' => 'Promise archived',
        'record_restored' => 'Promise restored',
        'record_deleted' => 'Promise deleted',
        'obligation_added' => 'Promise part added',
        'money_movement_recorded' => 'Money movement recorded',
        'money_movement_voided' => 'Money movement voided',
        'quantity_return_recorded' => 'Quantity returned',
        'quantity_return_corrected' => 'Quantity return corrected',
        'commitment_completed' => 'Commitment completed',
        'commitment_reopened' => 'Commitment reopened',
        'evidence_attached' => 'Evidence attached',
        'evidence_detached' => 'Evidence detached',
        'reminder_scheduled' => 'Reminder scheduled',
        'reminder_dismissed' => 'Reminder dismissed',
        'reminder_snoozed' => 'Reminder snoozed',
        'financial_profile_updated' => 'Profile settings updated',
        'api_token_created' => 'API token created',
        'api_token_revoked' => 'API token revoked',
        'member_invited' => 'Member invited',
        'member_invitation_accepted' => 'Member invitation accepted',
        'member_role_changed' => 'Member role changed',
        'member_removed' => 'Member removed',
        'party_archived' => 'Person archived',
        'party_restored' => 'Person restored',
        'party_contact_added' => 'Contact added',
        'party_contact_removed' => 'Contact removed',
        'party_contact_primary_set' => 'Primary contact changed',
        'payment_destination_added' => 'Payment destination added',
        'payment_destination_verified' => 'Payment destination verified',
        'payment_destination_removed' => 'Payment destination removed',
        'payment_destination_revealed' => 'Payment destination revealed',
        'party_relationship_added' => 'Party relationship added',
        'party_relationship_removed' => 'Party relationship removed',
        'exchange_rate_saved' => 'Exchange rate saved',
        'repayment_plan_generated' => 'Repayment plan generated',
    ];

    public static function label(string $action): string
    {
        return self::MAP[$action] ?? Str::headline($action);
    }
}
