<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\Obligation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateObligation
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array{
     *     title: string,
     *     tracking_mode: string,
     *     category: string,
     *     original_amount: string|null,
     *     current_principal_balance: string|null,
     *     current_total_balance: string|null,
     *     minimum_payment_amount: string|null,
     *     started_on: string|null,
     *     due_on: string|null,
     *     next_due_on: string|null,
     *     data_confidence: string,
     *     is_interest_bearing: bool,
     *     description: string,
     *     subject_name: string|null,
     *     subject_quantity: string|null,
     *     current_subject_quantity: string|null,
     *     quantity_mode: string|null,
     *     subject_unit: string|null,
     *     subject_condition: string|null,
     *     subject_details: string|null,
     *     asset_type: string|null,
     *     service_type: string|null,
     *     estimated_value: string|null,
     *     estimated_value_currency: string|null,
     *     completion_criteria: string|null,
     *     is_conditional: bool,
     *     condition_description: string|null,
     *     condition_triggered_on: string|null
     * } $data
     */
    public function handle(Obligation $obligation, array $data): Obligation
    {
        return DB::transaction(function () use ($obligation, $data): Obligation {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->with('record.profile')
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('update', $lockedObligation);

            $before = $lockedObligation->only([
                'title', 'category', 'original_amount', 'tracking_mode',
                'current_principal_balance', 'current_total_balance', 'minimum_payment_amount',
                'started_on', 'due_on', 'next_due_on', 'status', 'data_confidence',
                'is_interest_bearing', 'description', 'subject_name', 'subject_quantity',
                'current_subject_quantity', 'quantity_mode', 'subject_unit', 'subject_condition', 'subject_details',
                'asset_type', 'service_type', 'estimated_value', 'estimated_value_currency',
                'completion_criteria', 'is_conditional', 'condition_description', 'condition_triggered_on',
            ]);

            $kind = ObligationKind::from($lockedObligation->obligation_kind);
            $currency = $kind->isMoney() ? (string) $lockedObligation->currency : null;
            $quantityMode = $kind->isQuantityBased()
                ? QuantityMode::tryFrom((string) ($data['quantity_mode'] ?? ''))
                : QuantityMode::Countable;

            if ($quantityMode === null) {
                throw ValidationException::withMessages(['quantity_mode' => 'Choose whether this quantity is countable or measurable.']);
            }

            if ($kind->isQuantityBased()) {
                if ((filled($data['subject_quantity']) || filled($data['current_subject_quantity']))
                    && ! Quantity::isModeCompatible($quantityMode, $data['subject_unit'] ?? null)) {
                    throw ValidationException::withMessages([
                        'subject_unit' => 'Measurable quantities need a unit such as gram, kilogram, hour, or metre. Use whole units for complete items like cameras.',
                    ]);
                }

                foreach (['subject_quantity', 'current_subject_quantity'] as $field) {
                    if (! filled($data[$field])) {
                        continue;
                    }

                    try {
                        Quantity::normalise($data[$field], $quantityMode);
                    } catch (\InvalidArgumentException $exception) {
                        throw ValidationException::withMessages([$field => $exception->getMessage()]);
                    }
                }
            }

            if ($kind === ObligationKind::Asset && filled($data['estimated_value']) && ! Currency::isSupported($data['estimated_value_currency'])) {
                throw ValidationException::withMessages(['estimated_value_currency' => 'Choose a supported currency for the estimated value.']);
            }

            if ($kind->isMoney() && $data['tracking_mode'] === 'snapshot' && $lockedObligation->transactions()->exists()) {
                throw ValidationException::withMessages([
                    'tracking_mode' => 'This record has movement history, so it must remain on detailed ledger tracking.',
                ]);
            }

            $currentTotalBalance = $kind->isMoney()
                ? ($data['tracking_mode'] === 'snapshot'
                    ? $this->parseMoney($data['current_total_balance'], $currency, 'current_total_balance')
                    : (int) ($lockedObligation->current_total_balance ?? 0))
                : 0;
            $originalAmount = $kind->isMoney() ? $this->parseMoney($data['original_amount'], $currency, 'original_amount') : null;
            $minimumPayment = $kind->isMoney() ? $this->parseMoney($data['minimum_payment_amount'], $currency, 'minimum_payment_amount') : null;
            $estimatedValueCurrency = $kind === ObligationKind::Asset && filled($data['estimated_value_currency'])
                ? strtoupper((string) $data['estimated_value_currency'])
                : null;
            $estimatedValue = $kind === ObligationKind::Asset && $estimatedValueCurrency !== null
                ? $this->parseMoney($data['estimated_value'], $estimatedValueCurrency, 'estimated_value')
                : null;

            $lockedObligation->fill([
                'title' => $data['title'],
                'tracking_mode' => $kind->isMoney() ? $data['tracking_mode'] : 'snapshot',
                'category' => $data['category'],
                'description' => $data['description'] ?: null,
                'original_amount' => $originalAmount,
                'minimum_payment_amount' => $minimumPayment,
                'started_on' => $data['started_on'],
                'due_on' => $data['due_on'],
                'next_due_on' => $data['next_due_on'],
                'data_confidence' => $data['data_confidence'],
                'is_interest_bearing' => $kind->isMoney() && $data['is_interest_bearing'],
                'subject_name' => $kind->isQuantityBased() ? $data['subject_name'] : null,
                'subject_quantity' => $kind->isQuantityBased() ? $data['subject_quantity'] : null,
                'current_subject_quantity' => $kind->isQuantityBased() ? $data['current_subject_quantity'] : null,
                'quantity_mode' => $quantityMode->value,
                'subject_unit' => $kind->isQuantityBased() ? $data['subject_unit'] : null,
                'subject_condition' => $kind === ObligationKind::Asset ? $data['subject_condition'] : null,
                'subject_details' => $kind->isQuantityBased() ? $data['subject_details'] : null,
                'asset_type' => $kind === ObligationKind::Asset ? ($data['asset_type'] ?: 'physical') : null,
                'service_type' => $kind === ObligationKind::Service ? ($data['service_type'] ?: 'other') : null,
                'estimated_value' => $estimatedValue,
                'estimated_value_currency' => $estimatedValueCurrency,
                'completion_criteria' => in_array($kind, [ObligationKind::Action, ObligationKind::Service], true) ? $data['completion_criteria'] : null,
                'is_conditional' => $data['is_conditional'],
                'condition_description' => $data['is_conditional'] ? $data['condition_description'] : null,
                'condition_triggered_on' => $data['is_conditional'] ? $data['condition_triggered_on'] : null,
            ]);

            if ($kind->isMoney() && $data['tracking_mode'] === 'snapshot') {
                $lockedObligation->fill([
                    'current_principal_balance' => $this->parseMoney($data['current_principal_balance'], $currency, 'current_principal_balance'),
                    'current_total_balance' => $currentTotalBalance,
                    'currency_opening_balances' => [strtoupper((string) $lockedObligation->currency) => $currentTotalBalance],
                    'currency_balances' => [strtoupper((string) $lockedObligation->currency) => $currentTotalBalance],
                ]);
            }

            if ($kind->isMoney()) {
                if ($currentTotalBalance === 0) {
                    $lockedObligation->status = 'settled';
                    $lockedObligation->settled_at = now();
                } elseif ($lockedObligation->status === 'settled') {
                    $lockedObligation->status = 'active';
                    $lockedObligation->settled_at = null;
                }
            }

            $lockedObligation->save();

            $this->auditLogger->record(
                $lockedObligation->record->profile,
                null,
                Obligation::class,
                $lockedObligation->getKey(),
                'updated',
                before: $before,
                after: $lockedObligation->only(array_keys($before)),
            );

            return $lockedObligation->refresh();
        });
    }

    private function parseMoney(?string $value, ?string $currency, string $field): ?int
    {
        if ($value === null || $value === '' || $currency === null) {
            return null;
        }

        try {
            return MoneyAmount::fromMajor($value, $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
