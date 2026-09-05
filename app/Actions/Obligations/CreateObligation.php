<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateObligation
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array{
     *     direction: string,
     *     tracking_mode?: string,
     *     obligation_kind: string,
     *     title: string,
     *     category: string,
     *     currency: string|null,
     *     original_amount: string|null,
     *     current_total_balance: string|null,
     *     minimum_payment_amount: string|null,
     *     next_due_on: string|null,
     *     is_interest_bearing: bool,
     *     description: string,
     *     subject_name: string|null,
     *     subject_quantity: string|null,
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
    public function handle(User $user, Record $record, array $data): Obligation
    {
        $record->loadMissing('profile');
        Gate::forUser($user)->authorize('createObligation', $record->profile);

        $kind = ObligationKind::from($data['obligation_kind']);
        $quantityMode = $kind->isQuantityBased()
            ? QuantityMode::tryFrom((string) ($data['quantity_mode'] ?? ''))
            : QuantityMode::Countable;

        if ($quantityMode === null) {
            throw ValidationException::withMessages(['quantity_mode' => 'Choose whether this quantity is countable or measurable.']);
        }

        if ($kind->isQuantityBased() && filled($data['subject_quantity'])
            && ! Quantity::isModeCompatible($quantityMode, $data['subject_unit'] ?? null)) {
            throw ValidationException::withMessages([
                'subject_unit' => 'Measurable quantities need a unit such as gram, kilogram, hour, or metre. Use whole units for complete items like cameras.',
            ]);
        }

        if ($kind->isQuantityBased() && filled($data['subject_quantity'])) {
            try {
                Quantity::normalise($data['subject_quantity'], $quantityMode);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['subject_quantity' => $exception->getMessage()]);
            }
        }
        if ($kind->isMoney() && ! Currency::isSupported($data['currency'])) {
            throw ValidationException::withMessages(['currency' => 'Choose a supported currency.']);
        }
        if ($kind === ObligationKind::Asset && filled($data['estimated_value']) && ! Currency::isSupported($data['estimated_value_currency'])) {
            throw ValidationException::withMessages(['estimated_value_currency' => 'Choose a supported currency for the estimated value.']);
        }

        return DB::transaction(function () use ($record, $user, $data, $quantityMode): Obligation {
            $kind = ObligationKind::from($data['obligation_kind']);
            $currency = $kind->isMoney() ? strtoupper((string) $data['currency']) : null;
            $trackingMode = $data['tracking_mode'] ?? 'snapshot';
            $currentBalance = $kind->isMoney()
                ? ($trackingMode === 'ledger'
                    ? $this->parseMoneyOrZero($data['current_total_balance'], (string) $currency, 'current_total_balance')
                    : $this->parseMoney($data['current_total_balance'], (string) $currency, 'current_total_balance'))
                : null;
            $originalAmount = $kind->isMoney() ? $this->parseMoney($data['original_amount'], (string) $currency, 'original_amount') : null;
            $minimumPayment = $kind->isMoney() ? $this->parseMoney($data['minimum_payment_amount'], (string) $currency, 'minimum_payment_amount') : null;
            $estimatedValueCurrency = $kind === ObligationKind::Asset && filled($data['estimated_value_currency'])
                ? strtoupper((string) $data['estimated_value_currency'])
                : null;
            $estimatedValue = $kind === ObligationKind::Asset && $estimatedValueCurrency !== null
                ? $this->parseMoney($data['estimated_value'], $estimatedValueCurrency, 'estimated_value')
                : null;

            $subjectQuantity = $kind->isQuantityBased() && filled($data['subject_quantity'])
                ? Quantity::normalise($data['subject_quantity'], $quantityMode)
                : null;

            $obligation = $record->obligations()->create([
                'direction' => $data['direction'],
                'tracking_mode' => $kind->isMoney() ? $trackingMode : 'snapshot',
                'obligation_kind' => $kind->value,
                'title' => $data['title'],
                'category' => $data['category'],
                'description' => $data['description'] ?: null,
                'currency' => $currency,
                'original_amount' => $originalAmount,
                'current_principal_balance' => $currentBalance,
                'current_total_balance' => $currentBalance,
                'minimum_payment_amount' => $minimumPayment,
                'next_due_on' => $data['next_due_on'],
                'is_interest_bearing' => $kind->isMoney() && $data['is_interest_bearing'],
                'data_confidence' => 'partial',
                'subject_name' => $kind->isQuantityBased() ? $data['subject_name'] : null,
                'subject_quantity' => $subjectQuantity,
                'quantity_mode' => $quantityMode->value,
                'current_subject_quantity' => $subjectQuantity,
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
                'currency_opening_balances' => $kind->isMoney() ? [$currency => $currentBalance ?? 0] : null,
                'currency_balances' => $kind->isMoney() ? [$currency => $currentBalance ?? 0] : null,
            ]);

            $this->auditLogger->record(
                $record->profile,
                $user,
                Obligation::class,
                $obligation->getKey(),
                'created',
                after: $obligation->only([
                    'direction', 'obligation_kind', 'title', 'category', 'currency',
                    'current_total_balance', 'subject_name', 'subject_quantity', 'quantity_mode', 'service_type',
                ]),
            );

            return $obligation;
        });
    }

    private function parseMoney(?string $value, string $currency, string $field): ?int
    {
        try {
            return MoneyAmount::fromMajor($value, $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }

    private function parseMoneyOrZero(?string $value, string $currency, string $field): int
    {
        try {
            return MoneyAmount::fromMajorOrZero($value, $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}
