<?php

namespace App\Actions\Obligations;

use App\Domain\Money\MoneyAmount;
use App\Models\Obligation;
use App\Models\ObligationTerm;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveObligationTerm
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param array{
     *     calculation_method: string,
     *     interest_rate: string|null,
     *     interest_period: string|null,
     *     compounding_period: string|null,
     *     late_fee_amount: string|null,
     *     late_fee_rate: string|null,
     *     grace_period_days: int,
     *     late_fee_recurring: bool,
     *     storage_fee_amount: string|null,
     *     storage_fee_period: string|null,
     *     fixed_installment_amount: string|null,
     *     effective_from: string,
     *     source_note: string,
     *     source_verified: bool
     * } $data
     */
    public function handle(Obligation $obligation, array $data): ObligationTerm
    {
        return DB::transaction(function () use ($obligation, $data): ObligationTerm {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('manageTerms', $lockedObligation);

            $version = ((int) ObligationTerm::query()
                ->where('obligation_id', $lockedObligation->getKey())
                ->max('version')) + 1;

            $term = ObligationTerm::create([
                'obligation_id' => $lockedObligation->getKey(),
                'version' => $version,
                'calculation_method' => $data['calculation_method'],
                'interest_rate' => $data['interest_rate'],
                'interest_period' => $data['interest_period'],
                'compounding_period' => $data['compounding_period'],
                'late_fee_amount' => $this->parseMoney($data['late_fee_amount'], (string) $lockedObligation->currency, 'late_fee_amount'),
                'late_fee_rate' => $data['late_fee_rate'],
                'storage_fee_amount' => $this->parseMoney($data['storage_fee_amount'], (string) $lockedObligation->currency, 'storage_fee_amount'),
                'storage_fee_period' => $data['storage_fee_period'],
                'fixed_installment_amount' => $data['calculation_method'] === 'fixed_installment'
                    ? $this->parseMoney($data['fixed_installment_amount'], (string) $lockedObligation->currency, 'fixed_installment_amount')
                    : null,
                'source_snapshot' => [
                    'note' => $data['source_note'] ?: null,
                    'verified' => $data['source_verified'],
                ],
                'formula' => [
                    'grace_period_days' => $data['grace_period_days'],
                    'late_fee_recurring' => $data['late_fee_recurring'],
                ],
                'effective_from' => $data['effective_from'],
            ]);

            $this->auditLogger->record(
                $lockedObligation->record->profile,
                null,
                ObligationTerm::class,
                $term->getKey(),
                'created',
                after: $term->only(['obligation_id', 'version', 'calculation_method', 'effective_from', 'source_snapshot']),
            );

            return $term;
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
}
