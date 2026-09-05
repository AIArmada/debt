<?php

namespace App\Http\Resources;

use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Obligation */
class ObligationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'record_id' => $this->record_id,
            'direction' => $this->direction,
            'obligation_kind' => $this->obligation_kind,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'tracking_mode' => $this->tracking_mode,
            'currency' => $this->currency,
            'original_amount' => $this->money($this->original_amount, $this->currency),
            'original_amount_minor' => $this->original_amount,
            'current_principal_balance' => $this->money($this->current_principal_balance, $this->currency),
            'current_principal_balance_minor' => $this->current_principal_balance,
            'current_total_balance' => $this->money($this->current_total_balance, $this->currency),
            'current_total_balance_minor' => $this->current_total_balance,
            'currency_balances' => $this->moneyBalances(),
            'currency_balances_minor' => $this->obligation_kind === 'money' ? $this->currencyBalances() : [],
            'currency_positions' => $this->moneyPositions(),
            'currency_positions_minor' => $this->obligation_kind === 'money' ? $this->currencyPositions() : [],
            'current_position' => $this->obligation_kind === 'money' ? [
                'direction' => $this->currentPositionDirection(),
                'amount' => $this->money($this->currentPositionAmount(), $this->currency),
                'amount_minor' => $this->currentPositionAmount(),
                'label' => $this->currentPositionLabel(),
                'is_reversed' => $this->isPositionReversed(),
            ] : null,
            'subject_name' => $this->subject_name,
            'subject_quantity' => $this->subject_quantity,
            'current_subject_quantity' => $this->current_subject_quantity,
            'quantity_mode' => $this->isQuantityBased() ? $this->quantityMode()->value : null,
            'subject_unit' => $this->subject_unit,
            'subject_condition' => $this->subject_condition,
            'subject_details' => $this->subject_details,
            'asset_type' => $this->asset_type,
            'service_type' => $this->service_type,
            'estimated_value' => $this->money($this->estimated_value, $this->estimated_value_currency),
            'estimated_value_minor' => $this->estimated_value,
            'estimated_value_currency' => $this->estimated_value_currency,
            'completion_criteria' => $this->completion_criteria,
            'is_conditional' => $this->is_conditional,
            'condition_description' => $this->condition_description,
            'condition_triggered_on' => $this->dateString($this->getAttribute('condition_triggered_on')),
            'minimum_payment_amount' => $this->money($this->minimum_payment_amount, $this->currency),
            'minimum_payment_amount_minor' => $this->minimum_payment_amount,
            'next_due_on' => $this->dateString($this->getAttribute('next_due_on')),
            'data_confidence' => $this->data_confidence,
            'is_interest_bearing' => $this->is_interest_bearing,
            'parties' => $this->whenLoaded('partyLinks', fn (): array => $this->partyLinks->map(fn ($link): array => [
                'id' => $link->party_id,
                'name' => $link->party?->preferred_name,
                'kind' => $link->party?->kind,
                'role' => $link->role,
                'share_basis' => $link->share_basis,
                'share_percent' => $link->share_percent,
                'share_currency' => $link->share_currency,
            ])->values()->all()),
            'collection_schedules' => CollectionScheduleResource::collection($this->whenLoaded('collectionSchedules')),
            'delivery_instructions' => DeliveryInstructionResource::collection($this->whenLoaded('deliveryInstructions')),
            'events' => $this->whenLoaded('events', fn (): array => $this->events->map(fn ($event): array => [
                'id' => $event->getKey(),
                'event_type' => $event->event_type,
                'quantity' => $event->quantity,
                'quantity_effect' => $event->quantity_effect,
                'unit' => $event->unit,
                'occurred_on' => $this->dateString($event->getAttribute('occurred_on')),
                'note' => $event->note,
            ])->values()->all()),
            'transactions' => $this->whenLoaded('transactions', fn (): array => $this->transactions->map(fn ($transaction): array => [
                'id' => $transaction->getKey(),
                'repayment_plan_allocation_id' => $transaction->repayment_plan_allocation_id,
                'collection_schedule_id' => $transaction->collection_schedule_id,
                'entry_type' => $transaction->entry_type,
                'balance_effect' => $transaction->balance_effect,
                'status' => $transaction->status,
                'amount' => $this->money($transaction->amount, $transaction->currency),
                'amount_minor' => $transaction->amount,
                'currency' => $transaction->currency,
                'balance_before' => $this->money($transaction->balance_before, $transaction->currency),
                'balance_before_minor' => $transaction->balance_before,
                'balance_after' => $this->money($transaction->balance_after, $transaction->currency),
                'balance_after_minor' => $transaction->balance_after,
                'occurred_on' => $this->dateString($transaction->getAttribute('occurred_on')),
                'external_reference' => $transaction->external_reference,
                'note' => $transaction->note,
                'documents' => $transaction->relationLoaded('documents') ? $transaction->documents->map(fn ($document): array => [
                    'id' => $document->getKey(),
                    'evidence_type' => $document->evidence_type,
                    'title' => $document->title,
                    'source' => $document->source,
                    'external_url' => $document->external_url,
                    'content' => $document->content,
                    'original_filename' => $document->original_filename,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'has_file' => $document->mediaFile() !== null,
                    'category' => $document->category,
                    'verification_status' => $document->verification_status,
                    'captured_on' => $this->dateString($document->getAttribute('captured_on')),
                ])->values()->all() : [],
            ])->values()->all()),
        ];
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }

    private function money(int|string|null $amount, ?string $currency): ?string
    {
        return $amount === null || $currency === null ? null : MoneyAmount::majorInput((int) $amount, $currency);
    }

    /** @return array<string, string> */
    private function moneyBalances(): array
    {
        if ($this->obligation_kind !== 'money') {
            return [];
        }

        return collect($this->currencyBalances())->mapWithKeys(fn (int $amount, string $currency): array => [$currency => MoneyAmount::majorInput($amount, $currency)])->all();
    }

    /** @return array<string, array{direction: string, amount: string, label: string}> */
    private function moneyPositions(): array
    {
        if ($this->obligation_kind !== 'money') {
            return [];
        }

        return collect($this->currencyPositions())->mapWithKeys(fn (array $position): array => [$position['currency'] => [
            'direction' => $position['direction'],
            'amount' => MoneyAmount::majorInput((int) $position['amount'], $position['currency']),
            'label' => $position['label'],
        ]])->all();
    }
}
