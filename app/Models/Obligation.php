<?php

namespace App\Models;

use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use Database\Factories\ObligationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon|null $due_on
 * @property Carbon|null $next_due_on
 * @property numeric-string|null $subject_quantity
 * @property numeric-string|null $current_subject_quantity
 */
class Obligation extends Model
{
    /** @use HasFactory<ObligationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'record_id', 'direction', 'category', 'title', 'description',
        'status', 'tracking_mode', 'obligation_kind', 'currency', 'original_amount', 'current_principal_balance',
        'subject_name', 'subject_quantity', 'current_subject_quantity', 'subject_unit', 'subject_condition',
        'quantity_mode',
        'subject_details', 'asset_type', 'service_type', 'estimated_value', 'estimated_value_currency',
        'completion_criteria', 'is_conditional', 'condition_description', 'condition_triggered_on',
        'current_total_balance', 'currency_opening_balances', 'currency_balances', 'minimum_payment_amount', 'started_on', 'due_on',
        'next_due_on', 'data_confidence', 'is_interest_bearing',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'integer', 'current_principal_balance' => 'integer',
            'current_total_balance' => 'integer', 'minimum_payment_amount' => 'integer',
            'currency_opening_balances' => 'array', 'currency_balances' => 'array',
            'subject_quantity' => 'decimal:4', 'current_subject_quantity' => 'decimal:4', 'estimated_value' => 'integer',
            'quantity_mode' => QuantityMode::class,
            'started_on' => 'date', 'due_on' => 'date', 'next_due_on' => 'date', 'condition_triggered_on' => 'date',
            'settled_at' => 'datetime', 'is_interest_bearing' => 'boolean',
        ];
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return HasMany<RepaymentInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(RepaymentInstallment::class);
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /** @return HasMany<ObligationParty, $this> */
    public function partyLinks(): HasMany
    {
        return $this->hasMany(ObligationParty::class);
    }

    /** @return HasManyThrough<Party, ObligationParty, $this> */
    public function parties(): HasManyThrough
    {
        return $this->hasManyThrough(Party::class, ObligationParty::class, 'obligation_id', 'id', 'id', 'party_id');
    }

    /** @return HasMany<ObligationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ObligationEvent::class)->latest('occurred_on')->latest('created_at');
    }

    /** @return HasManyThrough<Document, DocumentLink, $this> */
    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, DocumentLink::class, 'obligation_id', 'id', 'id', 'document_id');
    }

    /** @return HasMany<CommunicationThread, $this> */
    public function communicationThreads(): HasMany
    {
        return $this->hasMany(CommunicationThread::class);
    }

    /** @return HasMany<PaymentSchedule, $this> */
    public function paymentSchedules(): HasMany
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    /** @return HasMany<CollectionSchedule, $this> */
    public function collectionSchedules(): HasMany
    {
        return $this->hasMany(CollectionSchedule::class)->latest();
    }

    /** @return HasMany<ObligationDeliveryInstruction, $this> */
    public function deliveryInstructions(): HasMany
    {
        return $this->hasMany(ObligationDeliveryInstruction::class)->latest();
    }

    /** @return HasMany<ObligationPaymentInstruction, $this> */
    public function paymentInstructions(): HasMany
    {
        return $this->hasMany(ObligationPaymentInstruction::class)->latest();
    }

    /** @return HasMany<PledgedAsset, $this> */
    public function pledgedAssets(): HasMany
    {
        return $this->hasMany(PledgedAsset::class);
    }

    /** @return HasMany<ObligationTerm, $this> */
    public function terms(): HasMany
    {
        return $this->hasMany(ObligationTerm::class)->orderByDesc('version');
    }

    /** @return HasMany<CalculationScenario, $this> */
    public function calculationScenarios(): HasMany
    {
        return $this->hasMany(CalculationScenario::class)->latest();
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'auditable_id')
            ->where('auditable_type', self::class)
            ->orderByDesc('occurred_at');
    }

    /** @param Builder<Obligation> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function kind(): ObligationKind
    {
        return ObligationKind::from($this->obligation_kind);
    }

    public function quantityMode(): QuantityMode
    {
        if (! $this->isQuantityBased()) {
            return QuantityMode::Countable;
        }

        /** @var QuantityMode $mode */
        $mode = $this->getAttribute('quantity_mode');

        return $mode;
    }

    public function quantityNeedsReview(): bool
    {
        if (! $this->isQuantityBased()) {
            return false;
        }

        $mode = $this->quantityMode();

        if ((filled($this->subject_quantity) || filled($this->current_subject_quantity))
            && ! Quantity::isModeCompatible($mode, $this->subject_unit)) {
            return true;
        }

        return (filled($this->subject_quantity) && ! Quantity::isValid($this->subject_quantity, $mode, false))
            || (filled($this->current_subject_quantity) && ! Quantity::isValid($this->current_subject_quantity, $mode, false));
    }

    public function kindLabel(): string
    {
        return $this->kind()->label();
    }

    public function categoryLabel(): string
    {
        return $this->kind()->categoryOptions()[$this->category]
            ?? Str::headline((string) $this->category);
    }

    public function isPawnCategory(): bool
    {
        return in_array($this->category, ['pawn_loan', 'pawned_asset'], true);
    }

    public function isQuantityBased(): bool
    {
        return $this->kind()->isQuantityBased();
    }

    public function isPositionReversed(): bool
    {
        return $this->kind()->isMoney() && (int) ($this->current_total_balance ?? 0) < 0;
    }

    /** @return array<string, int> */
    public function currencyBalances(): array
    {
        $balances = [];
        foreach ((array) $this->getAttribute('currency_balances') as $currency => $balance) {
            if (is_string($currency) && (is_int($balance) || (is_string($balance) && preg_match('/^-?\d+$/D', $balance) === 1))) {
                $balances[strtoupper($currency)] = (int) $balance;
            }
        }

        $primary = strtoupper((string) $this->currency);
        if ($primary !== '') {
            $balances[$primary] ??= (int) ($this->current_total_balance ?? 0);
        }

        return $balances;
    }

    /** @return array<string, int> */
    public function outstandingCurrencyBalances(): array
    {
        return array_filter(
            $this->currencyBalances(),
            fn (int $balance): bool => $balance !== 0,
        );
    }

    public function hasMultipleCurrencyExposures(): bool
    {
        return count($this->outstandingCurrencyBalances()) > 1;
    }

    /** @return array{currency: string, balance: int, direction: string|null, amount: int, label: string} */
    public function currencyPosition(string $currency): array
    {
        $currency = strtoupper($currency);
        $balance = $this->currencyBalances()[$currency] ?? 0;
        $comparison = $balance <=> 0;
        $direction = $comparison === 0
            ? null
            : ($comparison === 1 ? $this->direction : ($this->direction === 'payable' ? 'receivable' : 'payable'));
        $amount = abs($balance);

        return [
            'currency' => $currency,
            'balance' => $balance,
            'direction' => $direction,
            'amount' => $amount,
            'label' => match ($direction) {
                'payable' => 'You owe them',
                'receivable' => 'They owe you',
                default => 'Settled',
            },
        ];
    }

    /** @return list<array{currency: string, balance: int, direction: string|null, amount: int, label: string}> */
    public function currencyPositions(): array
    {
        return array_map(fn (string $currency): array => $this->currencyPosition($currency), array_keys($this->currencyBalances()));
    }

    public function currentPositionDirection(): ?string
    {
        if (! $this->kind()->isMoney()) {
            return null;
        }

        $balanceComparison = ((int) ($this->current_total_balance ?? 0)) <=> 0;

        if ($balanceComparison === 0) {
            return null;
        }

        if ($balanceComparison === 1) {
            return $this->direction;
        }

        return $this->direction === 'payable' ? 'receivable' : 'payable';
    }

    public function currentPositionAmount(): int
    {
        return abs((int) ($this->current_total_balance ?? 0));
    }

    public function currentPositionLabel(): string
    {
        return match ($this->currentPositionDirection()) {
            'payable' => 'You owe them',
            'receivable' => 'They owe you',
            default => 'Settled',
        };
    }

    public function effectiveDirection(): ?string
    {
        if (! $this->kind()->isMoney()) {
            return $this->direction;
        }

        $directions = $this->currentPositionDirections();

        return count($directions) === 1 ? $directions[0] : null;
    }

    public function effectiveDirectionLabel(): string
    {
        if ($this->kind()->isMoney() && count($this->currentPositionDirections()) > 1) {
            return 'Mixed currency position';
        }

        return match ($this->effectiveDirection()) {
            'payable' => 'You owe them',
            'receivable' => 'They owe you',
            default => 'Settled',
        };
    }

    /** @return list<string> */
    public function currentPositionDirections(): array
    {
        if (! $this->kind()->isMoney()) {
            return [$this->direction];
        }

        $directions = [];
        foreach ($this->currencyPositions() as $position) {
            if (is_string($position['direction'])) {
                $directions[$position['direction']] = true;
            }
        }

        return array_keys($directions);
    }

    public function hasCurrentDirection(string $direction): bool
    {
        return in_array($direction, $this->currentPositionDirections(), true);
    }

    public function originalDirectionLabel(): string
    {
        return $this->direction === 'payable' ? 'You owe them' : 'They owe you';
    }
}
