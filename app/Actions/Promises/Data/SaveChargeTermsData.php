<?php

namespace App\Actions\Promises\Data;

use App\Domain\Money\Money;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SaveChargeTermsData
{
    private function __construct(
        public string $currency,
        public string $mode,
        public string $kind,
        public ?string $rate,
        public ?string $basis,
        public ?string $period,
        public int $graceDays,
        public ?int $capMinor,
        public ?int $totalPayableMinor,
        public string $penaltyKind,
        public ?int $penaltyValue,
        public string $allocationOrder,
        public bool $isFormal,
        public bool $autoApplyOnAcceptance,
        public bool $fixedTotalAgreedUpfront,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        foreach (['rate', 'basis', 'period', 'cap', 'totalPayable', 'penaltyValue'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($input, [
            'currency' => ['required', 'string', 'size:3'],
            'mode' => ['required', 'string', 'in:none,manual,auto_propose,auto_apply'],
            'kind' => ['required', 'string', 'in:none,interest,profit'],
            'rate' => ['nullable', 'string', 'regex:/^\d{1,4}(?:\.\d{1,8})?$/'],
            'basis' => ['nullable', 'string', 'in:flat,reducing_simple,fixed_total'],
            'period' => ['nullable', 'string', 'in:daily,monthly,yearly'],
            'graceDays' => ['required', 'integer', 'min:0', 'max:36500'],
            'cap' => ['nullable', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'totalPayable' => ['nullable', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'penaltyKind' => ['required', 'string', 'in:none,fixed,percent_of_overdue'],
            'penaltyValue' => ['nullable', 'string'],
            'allocationOrder' => ['required', 'string', 'in:charges_first,principal_first'],
            'isFormal' => ['required', 'boolean'],
            'autoApplyOnAcceptance' => ['required', 'boolean'],
            'fixedTotalAgreedUpfront' => ['required', 'boolean'],
        ])->validate();

        $currency = StringNormalizer::uppercaseTrimmed((string) $validated['currency']);
        $capMinor = self::parseMinor($validated['cap'] ?? null, $currency, 'cap');
        $basis = self::optionalString($validated['basis'] ?? null);
        $totalPayableMinor = $basis === 'fixed_total'
            ? self::parseMinor($validated['totalPayable'] ?? null, $currency, 'totalPayable')
            : null;
        $penaltyValue = self::parsePenaltyValue(
            $validated['penaltyValue'] ?? null,
            (string) $validated['penaltyKind'],
            $currency,
        );

        return new self(
            currency: $currency,
            mode: (string) $validated['mode'],
            kind: (string) $validated['kind'],
            rate: $basis === 'fixed_total' ? null : self::optionalString($validated['rate'] ?? null),
            basis: $basis,
            period: self::optionalString($validated['period'] ?? null),
            graceDays: (int) $validated['graceDays'],
            capMinor: $capMinor,
            totalPayableMinor: $totalPayableMinor,
            penaltyKind: (string) $validated['penaltyKind'],
            penaltyValue: $penaltyValue,
            allocationOrder: (string) $validated['allocationOrder'],
            isFormal: filter_var($validated['isFormal'], FILTER_VALIDATE_BOOLEAN),
            autoApplyOnAcceptance: filter_var($validated['autoApplyOnAcceptance'], FILTER_VALIDATE_BOOLEAN),
            fixedTotalAgreedUpfront: filter_var($validated['fixedTotalAgreedUpfront'], FILTER_VALIDATE_BOOLEAN),
        );
    }

    private static function parseMinor(mixed $amount, string $currency, string $field): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        try {
            $money = Money::parseMajor((string) $amount, $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }

        return $money->amountMinor;
    }

    private static function parsePenaltyValue(mixed $value, string $penaltyKind, string $currency): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($penaltyKind === 'fixed') {
            $amountMinor = self::parseMinor($value, $currency, 'penaltyValue');
            if ($amountMinor !== null && $amountMinor <= 0) {
                throw ValidationException::withMessages(['penaltyValue' => 'Enter a fixed penalty greater than zero.']);
            }

            return $amountMinor;
        }

        if ($penaltyKind === 'percent_of_overdue'
            && (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > 10000)) {
            throw ValidationException::withMessages(['penaltyValue' => 'Enter percentage points in basis points from 1 to 10000.']);
        }

        return $penaltyKind === 'percent_of_overdue' ? (int) $value : null;
    }

    private static function optionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
