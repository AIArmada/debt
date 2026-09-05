<?php

namespace App\Http\Controllers;

use App\Domain\Money\MoneyAmount;
use App\Models\BankImport;
use App\Models\BankImportRow;
use App\Models\BudgetPeriod;
use App\Models\CalculationScenario;
use App\Models\CashFlowEntry;
use App\Models\CollectionAccount;
use App\Models\CollectionSchedule;
use App\Models\Document;
use App\Models\EmergencyAccessRequest;
use App\Models\ExchangeRate;
use App\Models\FinancialProfile;
use App\Models\FinancialTransaction;
use App\Models\Integration;
use App\Models\Obligation;
use App\Models\ObligationDeliveryInstruction;
use App\Models\ObligationEvent;
use App\Models\ObligationTerm;
use App\Models\Party;
use App\Models\PartyContactRoute;
use App\Models\PartyPaymentDestination;
use App\Models\PaymentAuthorisation;
use App\Models\PaymentExecutionAttempt;
use App\Models\PaymentSchedule;
use App\Models\PledgedAsset;
use App\Models\ProfileInvitation;
use App\Models\ProfileMember;
use App\Models\Record;
use App\Models\RepaymentInstallment;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportUserDataController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        $profiles = FinancialProfile::query()
            ->where('owner_user_id', $user->getKey())
            ->with([
                'parties.contacts',
                'parties.addresses',
                'parties.paymentDestinations',
                'parties.contactRoutes.viaParty',
                'parties.contactRoutes.viaContact',
                'records.partyLinks.party.contacts',
                'records.partyLinks.party.addresses',
                'records.partyLinks.party.paymentDestinations',
                'records.partyLinks.party.contactRoutes.viaParty',
                'records.partyLinks.party.contactRoutes.viaContact',
                'records.obligations.transactions',
                'records.obligations.partyLinks.party',
                'records.obligations.transactions.partyLinks.party',
                'records.obligations.transactions.documents',
                'records.obligations.transactions.documents.media',
                'records.obligations.events.documents',
                'records.obligations.events.documents.media',
                'records.obligations.events.createdBy',
                'records.documents',
                'records.documents.media',
                'budgetPeriods.cashFlowEntries',
                'repaymentPlans.allocations.obligation',
                'repaymentPlans.allocations.paidTransactions',
                'members.user',
                'invitations',
                'exchangeRates',
                'integrations',
                'emergencyAccessRequests.user',
                'bankImports.rows',
                'bankImports.media',
                'records.obligations.terms',
                'records.obligations.pledgedAssets',
                'records.obligations.calculationScenarios',
                'records.obligations.installments',
                'records.obligations.paymentSchedules.authorisations',
                'records.obligations.paymentSchedules.executionAttempts',
                'records.obligations.collectionSchedules.collectionAccount',
                'records.obligations.deliveryInstructions.address',
                'records.obligations.deliveryInstructions.recipientParty',
                'bankImports.rows.financialTransaction',
                'collectionAccounts',
            ])
            ->get();

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'user' => ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email],
            'profiles' => $profiles->map(fn (FinancialProfile $profile): array => [
                'id' => $profile->getKey(),
                'name' => $profile->name,
                'type' => $profile->type,
                'base_currency' => $profile->base_currency,
                'timezone' => $profile->timezone,
                'locale' => $profile->locale,
                'is_islamic_mode_enabled' => $profile->is_islamic_mode_enabled,
                'exchange_rates' => $profile->exchangeRates->map(fn (ExchangeRate $rate): array => [
                    'from_currency' => $rate->from_currency,
                    'to_currency' => $rate->to_currency,
                    'rate' => $rate->rate,
                    'source' => $rate->source,
                    'effective_on' => $this->dateString($rate->getAttribute('effective_on')),
                ])->values()->all(),
                'integrations' => $profile->integrations->map(fn (Integration $integration): array => [
                    'provider' => $integration->provider,
                    'type' => $integration->type,
                    'status' => $integration->status,
                    'metadata' => $integration->metadata,
                    'last_synced_at' => $this->isoDateTime($integration->getAttribute('last_synced_at')),
                    'revoked_at' => $this->isoDateTime($integration->getAttribute('revoked_at')),
                ])->values()->all(),
                'emergency_access_requests' => $profile->emergencyAccessRequests->map(fn (EmergencyAccessRequest $accessRequest): array => [
                    'user_email' => $accessRequest->user?->email,
                    'status' => $accessRequest->status,
                    'reason' => $accessRequest->reason,
                    'activate_after' => $this->isoDateTime($accessRequest->getAttribute('activate_after')),
                    'approved_at' => $this->isoDateTime($accessRequest->getAttribute('approved_at')),
                    'activated_at' => $this->isoDateTime($accessRequest->getAttribute('activated_at')),
                    'expires_at' => $this->isoDateTime($accessRequest->getAttribute('expires_at')),
                ])->values()->all(),
                'members' => $profile->members->map(fn (ProfileMember $member): array => [
                    'email' => $member->user->email,
                    'role' => $member->role,
                    'accepted_at' => $this->isoDateTime($member->getAttribute('accepted_at')),
                ])->values()->all(),
                'invitations' => $profile->invitations->map(fn (ProfileInvitation $invitation): array => [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'expires_at' => $this->isoDateTime($invitation->getAttribute('expires_at')),
                    'accepted_at' => $this->isoDateTime($invitation->getAttribute('accepted_at')),
                    'revoked_at' => $this->isoDateTime($invitation->getAttribute('revoked_at')),
                ])->values()->all(),
                'collection_accounts' => $profile->collectionAccounts->map(fn (CollectionAccount $account): array => [
                    'id' => $account->getKey(),
                    'method' => $account->method,
                    'label' => $account->label,
                    'provider' => $account->provider,
                    'country_code' => $account->country_code,
                    'currency' => $account->currency,
                    'account_holder_name' => $account->account_holder_name,
                    'masked_identifier' => $account->maskedIdentifier(),
                    'verification_status' => $account->verification_status,
                    'verified_at' => $this->isoDateTime($account->getAttribute('verified_at')),
                    'superseded_at' => $this->isoDateTime($account->getAttribute('superseded_at')),
                    'status' => $account->status,
                ])->values()->all(),
                'parties' => $profile->parties->map(fn (Party $party): array => [
                    'id' => $party->getKey(),
                    'kind' => $party->kind,
                    'preferred_name' => $party->preferred_name,
                    'legal_name' => $party->legal_name,
                    'aliases' => $party->aliases,
                    'identifiers' => $party->identifiers,
                    'status' => $party->status,
                    'verification_status' => $party->verification_status,
                    'contacts' => $party->contacts->map(fn ($contact): array => [
                        'id' => $contact->getKey(),
                        'type' => $contact->type,
                        'label' => $contact->label,
                        'value' => $contact->value,
                        'purpose' => $contact->purpose,
                        'is_primary' => $contact->is_primary,
                        'is_message_safe' => $contact->is_message_safe,
                    ])->values()->all(),
                    'addresses' => $party->addresses->map(fn ($address): array => [
                        'id' => $address->getKey(),
                        'label' => $address->label,
                        'address_line_1' => $address->address_line_1,
                        'address_line_2' => $address->address_line_2,
                        'city' => $address->city,
                        'region' => $address->region,
                        'postal_code' => $address->postal_code,
                        'country_code' => $address->country_code,
                        'purpose' => $address->purpose,
                    ])->values()->all(),
                    'payment_destinations' => $party->paymentDestinations->map(fn (PartyPaymentDestination $destination): array => [
                        'id' => $destination->getKey(),
                        'method' => $destination->method,
                        'label' => $destination->label,
                        'provider' => $destination->provider,
                        'country_code' => $destination->country_code,
                        'currency' => $destination->currency,
                        'account_holder_name' => $destination->account_holder_name,
                        'masked_identifier' => $destination->maskedIdentifier(),
                        'reference_template' => $destination->reference_template,
                        'verification_status' => $destination->verification_status,
                        'verified_at' => $this->isoDateTime($destination->getAttribute('verified_at')),
                        'superseded_at' => $this->isoDateTime($destination->getAttribute('superseded_at')),
                        'status' => $destination->status,
                    ])->values()->all(),
                    'contact_routes' => $party->contactRoutes->map(fn (PartyContactRoute $route): array => [
                        'id' => $route->getKey(),
                        'via_party_id' => $route->via_party_id,
                        'via_party_name' => $route->viaParty?->preferred_name,
                        'via_contact_id' => $route->via_contact_id,
                        'via_contact' => $route->viaContact?->value,
                        'relationship_type' => $route->relationship_type,
                        'purpose' => $route->purpose,
                        'priority' => $route->priority,
                        'is_primary' => $route->is_primary,
                        'status' => $route->status,
                        'instructions' => $route->instructions,
                        'valid_from' => $this->dateString($route->getAttribute('valid_from')),
                        'valid_to' => $this->dateString($route->getAttribute('valid_to')),
                    ])->values()->all(),
                ])->values()->all(),
                'records' => $profile->records->map(fn (Record $record): array => [
                    'id' => $record->getKey(),
                    'title' => $record->title,
                    'description' => $record->description,
                    'sensitivity' => $record->sensitivity,
                    'is_archived' => $record->is_archived,
                    'parties' => $record->partyLinks->map(fn ($link): array => [
                        'id' => $link->party_id,
                        'name' => $link->party?->preferred_name,
                        'kind' => $link->party?->kind,
                        'role' => $link->role,
                        'is_primary' => $link->is_primary,
                        'status' => $link->status,
                        'contacts' => $link->party?->contacts->map(fn ($contact): array => [
                            'type' => $contact->type,
                            'label' => $contact->label,
                            'value' => $contact->value,
                            'purpose' => $contact->purpose,
                        ])->values()->all(),
                        'addresses' => $link->party?->addresses->map(fn ($address): array => [
                            'label' => $address->label,
                            'address_line_1' => $address->address_line_1,
                            'address_line_2' => $address->address_line_2,
                            'city' => $address->city,
                            'region' => $address->region,
                            'postal_code' => $address->postal_code,
                            'country_code' => $address->country_code,
                        ])->values()->all(),
                    ])->values()->all(),
                    'shared_documents' => $record->documents->map(fn (Document $document): array => [
                        'evidence_type' => $document->evidence_type,
                        'title' => $document->title,
                        'source' => $document->source,
                        'external_url' => $document->external_url,
                        'content' => $document->content,
                        'original_filename' => $document->original_filename,
                        'mime_type' => $document->mime_type,
                        'size_bytes' => $document->size_bytes,
                        'category' => $document->category,
                        'verification_status' => $document->verification_status,
                    ])->values()->all(),
                    'obligations' => $record->obligations->map(fn (Obligation $obligation): array => [
                        'id' => $obligation->getKey(),
                        'direction' => $obligation->direction,
                        'obligation_kind' => $obligation->obligation_kind,
                        'category' => $obligation->category,
                        'title' => $obligation->title,
                        'description' => $obligation->description,
                        'status' => $obligation->status,
                        'tracking_mode' => $obligation->tracking_mode,
                        'currency' => $obligation->currency,
                        'original_amount' => $this->money($obligation->original_amount, $obligation->currency),
                        'original_amount_minor' => $obligation->original_amount,
                        'current_principal_balance' => $this->money($obligation->current_principal_balance, $obligation->currency),
                        'current_principal_balance_minor' => $obligation->current_principal_balance,
                        'current_total_balance' => $this->money($obligation->current_total_balance, $obligation->currency),
                        'current_total_balance_minor' => $obligation->current_total_balance,
                        'current_position' => $obligation->obligation_kind === 'money' ? [
                            'direction' => $obligation->currentPositionDirection(),
                            'amount' => $this->money($obligation->currentPositionAmount(), $obligation->currency),
                            'amount_minor' => $obligation->currentPositionAmount(),
                            'label' => $obligation->currentPositionLabel(),
                            'is_reversed' => $obligation->isPositionReversed(),
                        ] : null,
                        'subject_name' => $obligation->subject_name,
                        'subject_quantity' => $obligation->subject_quantity,
                        'current_subject_quantity' => $obligation->current_subject_quantity,
                        'subject_unit' => $obligation->subject_unit,
                        'subject_condition' => $obligation->subject_condition,
                        'subject_details' => $obligation->subject_details,
                        'asset_type' => $obligation->asset_type,
                        'service_type' => $obligation->service_type,
                        'estimated_value' => $this->money($obligation->estimated_value, $obligation->estimated_value_currency),
                        'estimated_value_minor' => $obligation->estimated_value,
                        'estimated_value_currency' => $obligation->estimated_value_currency,
                        'completion_criteria' => $obligation->completion_criteria,
                        'is_conditional' => $obligation->is_conditional,
                        'condition_description' => $obligation->condition_description,
                        'condition_triggered_on' => $this->dateString($obligation->getAttribute('condition_triggered_on')),
                        'minimum_payment_amount' => $this->money($obligation->minimum_payment_amount, $obligation->currency),
                        'minimum_payment_amount_minor' => $obligation->minimum_payment_amount,
                        'started_on' => $this->dateString($obligation->getAttribute('started_on')),
                        'due_on' => $this->dateString($obligation->getAttribute('due_on')),
                        'next_due_on' => $this->dateString($obligation->getAttribute('next_due_on')),
                        'data_confidence' => $obligation->data_confidence,
                        'is_interest_bearing' => $obligation->is_interest_bearing,
                        'parties' => $obligation->partyLinks->map(fn ($link): array => [
                            'party_id' => $link->party_id,
                            'name' => $link->party?->preferred_name,
                            'role' => $link->role,
                            'share_basis' => $link->share_basis,
                            'share_percent' => $link->share_percent,
                            'share_amount_minor' => $link->share_amount,
                            'share_currency' => $link->share_currency,
                        ])->values()->all(),
                        'terms' => $obligation->terms->map(fn (ObligationTerm $term): array => [
                            'version' => $term->version,
                            'calculation_method' => $term->calculation_method,
                            'interest_rate' => $term->interest_rate,
                            'interest_period' => $term->interest_period,
                            'compounding_period' => $term->compounding_period,
                            'late_fee_amount' => $this->money($term->late_fee_amount, $obligation->currency),
                            'late_fee_amount_minor' => $term->late_fee_amount,
                            'late_fee_rate' => $term->late_fee_rate,
                            'storage_fee_amount' => $this->money($term->storage_fee_amount, $obligation->currency),
                            'storage_fee_amount_minor' => $term->storage_fee_amount,
                            'storage_fee_period' => $term->storage_fee_period,
                            'formula' => $term->formula,
                            'source_snapshot' => $term->source_snapshot,
                            'effective_from' => $this->dateString($term->getAttribute('effective_from')),
                            'effective_to' => $this->dateString($term->getAttribute('effective_to')),
                        ])->values()->all(),
                        'pledged_assets' => $obligation->pledgedAssets->map(fn (PledgedAsset $asset): array => [
                            'asset_type' => $asset->asset_type,
                            'description' => $asset->description,
                            'quantity' => $asset->quantity,
                            'quantity_mode' => $asset->quantity_mode->value,
                            'quantity_unit' => $asset->quantity_unit,
                            'estimated_value' => $this->money($asset->estimated_value, $asset->currency),
                            'estimated_value_minor' => $asset->estimated_value,
                            'currency' => $asset->currency,
                            'storage_location' => $asset->storage_location,
                            'pledged_on' => $this->dateString($asset->getAttribute('pledged_on')),
                            'matures_on' => $this->dateString($asset->getAttribute('matures_on')),
                            'status' => $asset->status,
                        ])->values()->all(),
                        'calculation_scenarios' => $obligation->calculationScenarios->map(fn (CalculationScenario $scenario): array => [
                            'name' => $scenario->name,
                            'extra_payment' => $this->money($scenario->extra_payment, $obligation->currency),
                            'extra_payment_minor' => $scenario->extra_payment,
                            'payment_frequency' => $scenario->payment_frequency,
                            'horizon_months' => $scenario->horizon_months,
                            'result' => $scenario->result,
                        ])->values()->all(),
                        'installments' => $obligation->installments->map(fn (RepaymentInstallment $installment): array => [
                            'sequence' => $installment->sequence,
                            'due_on' => $this->dateString($installment->getAttribute('due_on')),
                            'expected_amount' => $this->money($installment->expected_amount, $obligation->currency),
                            'expected_amount_minor' => $installment->expected_amount,
                            'principal_amount' => $this->money($installment->principal_amount, $obligation->currency),
                            'principal_amount_minor' => $installment->principal_amount,
                            'interest_amount' => $this->money($installment->interest_amount, $obligation->currency),
                            'interest_amount_minor' => $installment->interest_amount,
                            'fee_amount' => $this->money($installment->fee_amount, $obligation->currency),
                            'fee_amount_minor' => $installment->fee_amount,
                            'status' => $installment->status,
                        ])->values()->all(),
                        'payment_schedules' => $obligation->paymentSchedules->map(fn (PaymentSchedule $schedule): array => [
                            'mode' => $schedule->mode,
                            'status' => $schedule->status,
                            'amount' => $this->money($schedule->amount, $schedule->currency),
                            'amount_minor' => $schedule->amount,
                            'currency' => $schedule->currency,
                            'frequency' => $schedule->frequency,
                            'starts_on' => $this->dateString($schedule->getAttribute('starts_on')),
                            'ends_on' => $this->dateString($schedule->getAttribute('ends_on')),
                            'next_runs_on' => $this->dateString($schedule->getAttribute('next_runs_on')),
                            'per_payment_limit' => $this->money($schedule->per_payment_limit, $schedule->currency),
                            'per_payment_limit_minor' => $schedule->per_payment_limit,
                            'authorised_at' => $this->isoDateTime($schedule->getAttribute('authorised_at')),
                            'authorisations' => $schedule->authorisations->map(fn (PaymentAuthorisation $authorisation): array => [
                                'provider' => $authorisation->provider,
                                'status' => $authorisation->status,
                                'max_amount' => $this->money($authorisation->max_amount, $schedule->currency),
                                'max_amount_minor' => $authorisation->max_amount,
                                'approved_at' => $this->isoDateTime($authorisation->getAttribute('approved_at')),
                                'revoked_at' => $this->isoDateTime($authorisation->getAttribute('revoked_at')),
                            ])->values()->all(),
                            'execution_attempts' => $schedule->executionAttempts->map(fn (PaymentExecutionAttempt $attempt): array => [
                                'idempotency_key' => $attempt->idempotency_key,
                                'provider' => $attempt->provider,
                                'amount' => $this->money($attempt->amount, $attempt->currency),
                                'amount_minor' => $attempt->amount,
                                'currency' => $attempt->currency,
                                'status' => $attempt->status,
                                'external_reference' => $attempt->external_reference,
                                'error_message' => $attempt->error_message,
                                'attempted_at' => $this->isoDateTime($attempt->getAttribute('attempted_at')),
                                'completed_at' => $this->isoDateTime($attempt->getAttribute('completed_at')),
                            ])->values()->all(),
                        ])->values()->all(),
                        'collection_schedules' => $obligation->collectionSchedules->map(fn (CollectionSchedule $schedule): array => [
                            'id' => $schedule->getKey(),
                            'collection_account_id' => $schedule->collection_account_id,
                            'mode' => $schedule->mode,
                            'status' => $schedule->status,
                            'amount' => $this->money($schedule->amount, $schedule->currency),
                            'amount_minor' => $schedule->amount,
                            'currency' => $schedule->currency,
                            'frequency' => $schedule->frequency,
                            'starts_on' => $this->dateString($schedule->getAttribute('starts_on')),
                            'ends_on' => $this->dateString($schedule->getAttribute('ends_on')),
                            'next_due_on' => $this->dateString($schedule->getAttribute('next_due_on')),
                            'collection_method' => $schedule->collection_method,
                            'grace_days' => $schedule->grace_days,
                            'note' => $schedule->note,
                            'paused_at' => $this->isoDateTime($schedule->getAttribute('paused_at')),
                        ])->values()->all(),
                        'transactions' => $obligation->transactions->map(fn (FinancialTransaction $transaction): array => [
                            'entry_type' => $transaction->entry_type,
                            'balance_effect' => $transaction->balance_effect,
                            'status' => $transaction->status,
                            'amount' => $this->money($transaction->amount, $transaction->currency),
                            'amount_minor' => $transaction->amount,
                            'balance_before' => $this->money($transaction->balance_before, $transaction->currency),
                            'balance_before_minor' => $transaction->balance_before,
                            'balance_after' => $this->money($transaction->balance_after, $transaction->currency),
                            'balance_after_minor' => $transaction->balance_after,
                            'currency' => $transaction->currency,
                            'repayment_plan_allocation_id' => $transaction->repayment_plan_allocation_id,
                            'collection_schedule_id' => $transaction->collection_schedule_id,
                            'occurred_on' => $this->dateString($transaction->getAttribute('occurred_on')),
                            'external_reference' => $transaction->external_reference,
                            'note' => $transaction->note,
                            'documents' => $transaction->documents->map(fn (Document $document): array => [
                                'evidence_type' => $document->evidence_type,
                                'title' => $document->title,
                                'source' => $document->source,
                                'external_url' => $document->external_url,
                                'content' => $document->content,
                                'original_filename' => $document->original_filename,
                                'mime_type' => $document->mime_type,
                                'size_bytes' => $document->size_bytes,
                                'category' => $document->category,
                                'verification_status' => $document->verification_status,
                                'ocr_status' => $document->ocr_status,
                                'ocr_provider' => $document->ocr_provider,
                                'extracted_text' => $document->extracted_text,
                                'ocr_completed_at' => $this->isoDateTime($document->getAttribute('ocr_completed_at')),
                                'captured_on' => $this->dateString($document->getAttribute('captured_on')),
                            ])->values()->all(),
                        ])->values()->all(),
                        'events' => $obligation->events->map(fn (ObligationEvent $event): array => [
                            'event_type' => $event->event_type,
                            'quantity' => $event->quantity,
                            'quantity_effect' => $event->quantity_effect,
                            'unit' => $event->unit,
                            'occurred_on' => $this->dateString($event->getAttribute('occurred_on')),
                            'note' => $event->note,
                            'created_by' => $event->createdBy?->email,
                            'metadata' => $event->metadata,
                            'documents' => $event->documents->map(fn (Document $document): array => [
                                'evidence_type' => $document->evidence_type,
                                'title' => $document->title,
                                'source' => $document->source,
                                'external_url' => $document->external_url,
                                'content' => $document->content,
                                'original_filename' => $document->original_filename,
                                'mime_type' => $document->mime_type,
                                'size_bytes' => $document->size_bytes,
                                'category' => $document->category,
                                'verification_status' => $document->verification_status,
                                'ocr_status' => $document->ocr_status,
                                'ocr_provider' => $document->ocr_provider,
                                'extracted_text' => $document->extracted_text,
                                'ocr_completed_at' => $this->isoDateTime($document->getAttribute('ocr_completed_at')),
                                'captured_on' => $this->dateString($document->getAttribute('captured_on')),
                            ])->values()->all(),
                        ])->values()->all(),
                        'documents' => $obligation->documents->map(fn (Document $document): array => [
                            'evidence_type' => $document->evidence_type,
                            'title' => $document->title,
                            'source' => $document->source,
                            'external_url' => $document->external_url,
                            'content' => $document->content,
                            'original_filename' => $document->original_filename,
                            'mime_type' => $document->mime_type,
                            'size_bytes' => $document->size_bytes,
                            'category' => $document->category,
                            'verification_status' => $document->verification_status,
                            'ocr_status' => $document->ocr_status,
                            'ocr_provider' => $document->ocr_provider,
                            'extracted_text' => $document->extracted_text,
                            'ocr_completed_at' => $this->isoDateTime($document->getAttribute('ocr_completed_at')),
                            'captured_on' => $this->dateString($document->getAttribute('captured_on')),
                        ])->values()->all(),
                        'delivery_instructions' => $obligation->deliveryInstructions->map(fn (ObligationDeliveryInstruction $instruction): array => [
                            'id' => $instruction->getKey(),
                            'address_id' => $instruction->address_id,
                            'recipient_party_id' => $instruction->recipient_party_id,
                            'method' => $instruction->method,
                            'label' => $instruction->label,
                            'instructions' => $instruction->instructions,
                            'status' => $instruction->status,
                            'verification_status' => $instruction->verification_status,
                            'verified_at' => $this->isoDateTime($instruction->getAttribute('verified_at')),
                            'superseded_at' => $this->isoDateTime($instruction->getAttribute('superseded_at')),
                            'shown_snapshot' => $instruction->shown_snapshot,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
                'budget_periods' => $profile->budgetPeriods->map(fn (BudgetPeriod $budget): array => [
                    'currency' => $budget->currency,
                    'starts_on' => $this->dateString($budget->getAttribute('starts_on')),
                    'ends_on' => $this->dateString($budget->getAttribute('ends_on')),
                    'emergency_reserve_amount' => $this->money($budget->emergency_reserve_amount, $budget->currency),
                    'emergency_reserve_amount_minor' => $budget->emergency_reserve_amount,
                    'cash_flow_entries' => $budget->cashFlowEntries->map(fn (CashFlowEntry $entry): array => [
                        'type' => $entry->type,
                        'category' => $entry->category,
                        'name' => $entry->name,
                        'amount' => $this->money($entry->amount, $budget->currency),
                        'amount_minor' => $entry->amount,
                        'is_essential' => $entry->is_essential,
                        'is_recurring' => $entry->is_recurring,
                    ])->values()->all(),
                ])->values()->all(),
                'repayment_plans' => $profile->repaymentPlans->map(fn ($plan): array => [
                    'id' => $plan->getKey(),
                    'budget_period_id' => $plan->budget_period_id,
                    'name' => $plan->name,
                    'strategy' => $plan->strategy,
                    'available_amount' => $this->money($plan->available_amount, $plan->currency),
                    'available_amount_minor' => $plan->available_amount,
                    'currency' => $plan->currency,
                    'status' => $plan->status,
                    'review_reason' => $plan->review_reason,
                    'review_required_at' => $this->isoDateTime($plan->getAttribute('review_required_at')),
                    'completed_at' => $this->isoDateTime($plan->getAttribute('completed_at')),
                    'superseded_at' => $this->isoDateTime($plan->getAttribute('superseded_at')),
                    'paused_at' => $this->isoDateTime($plan->getAttribute('paused_at')),
                    'activated_at' => $this->isoDateTime($plan->getAttribute('activated_at')),
                    'generated_at' => $this->isoDateTime($plan->getAttribute('generated_at')),
                    'allocations' => $plan->allocations->map(fn ($allocation): array => [
                        'obligation_id' => $allocation->obligation_id,
                        'currency' => $allocation->currency,
                        'priority_rank' => $allocation->priority_rank,
                        'minimum_amount' => $this->money($allocation->minimum_amount, $allocation->currency),
                        'minimum_amount_minor' => $allocation->minimum_amount,
                        'extra_amount' => $this->money($allocation->extra_amount, $allocation->currency),
                        'extra_amount_minor' => $allocation->extra_amount,
                        'total_amount' => $this->money($allocation->total_amount, $allocation->currency),
                        'total_amount_minor' => $allocation->total_amount,
                        'carried_paid_amount' => $this->money($allocation->carried_paid_amount, $allocation->currency),
                        'carried_paid_amount_minor' => $allocation->carried_paid_amount,
                        'priority_reason' => $allocation->priority_reason,
                        'projected_completion_on' => $this->dateString($allocation->getAttribute('projected_completion_on')),
                        'payment_movement_ids' => $allocation->paidTransactions->pluck('id')->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
                'bank_imports' => $profile->bankImports->map(fn (BankImport $import): array => [
                    'original_filename' => $import->original_filename,
                    'mime_type' => $import->mime_type,
                    'size_bytes' => $import->size_bytes,
                    'format' => $import->format,
                    'currency' => $import->currency,
                    'status' => $import->status,
                    'row_count' => $import->row_count,
                    'matched_count' => $import->matched_count,
                    'error_message' => $import->error_message,
                    'rows' => $import->rows->map(fn (BankImportRow $row): array => [
                        'row_number' => $row->row_number,
                        'occurred_on' => $this->dateString($row->getAttribute('occurred_on')),
                        'description' => $row->description,
                        'amount' => $this->money($row->amount, $row->currency),
                        'amount_minor' => $row->amount,
                        'currency' => $row->currency,
                        'suggested_direction' => $row->suggested_direction,
                        'external_reference' => $row->external_reference,
                        'status' => $row->status,
                        'financial_transaction_id' => $row->financial_transaction_id,
                        'raw_data' => $row->raw_data,
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];

        $payload['notification_preferences'] = $user->notificationPreference()->first()?->only(['email_enabled', 'in_app_enabled', 'push_enabled', 'generic_push', 'due_reminder_days']);
        $payload['notifications'] = $user->notifications()->get()->map(fn (DatabaseNotification $notification): array => [
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $this->isoDateTime($notification->getAttribute('read_at')),
            'created_at' => $this->isoDateTime($notification->getAttribute('created_at')),
        ])->values()->all();

        return response()->streamDownload(function () use ($payload): void {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        }, 'debt-management-export-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json']);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    private function isoDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : (string) $value;
    }

    private function money(int|string|null $amount, ?string $currency): ?string
    {
        return $amount === null || $currency === null ? null : MoneyAmount::majorInput((int) $amount, $currency);
    }
}
