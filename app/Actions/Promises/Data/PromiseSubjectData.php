<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\SubjectType;
use App\Domain\Money\Money;
use App\Domain\Quantities\QuantityValidator;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class PromiseSubjectData
{
    private function __construct(
        public SubjectType $type,
        public int $amountMinor,
        public string $currency,
        public ?string $quantityName,
        public ?string $quantityTotal,
        public ?string $quantityUnit,
        public bool $isFractionable,
        public ?string $doneCriteria,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'subjectType' => ['required', Rule::enum(SubjectType::class)],
            'amount' => ['nullable', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'quantityName' => ['nullable', 'string', 'max:255'],
            'quantityTotal' => ['nullable', 'string', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'quantityUnit' => ['nullable', 'string', 'max:60'],
            'isFractionable' => ['sometimes', 'boolean'],
            'doneCriteria' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, string $currency): self
    {
        $validated = Validator::make($input + ['subjectType' => SubjectType::Money->value], self::rules())->validate();
        $type = SubjectType::from((string) $validated['subjectType']);
        $amountMinor = 0;
        $moneyCurrency = StringNormalizer::uppercase($currency);

        if ($type === SubjectType::Money) {
            if (! filled($validated['amount'] ?? null)) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
            }

            try {
                $money = Money::parseMajor((string) $validated['amount'], $currency);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            if ($money->amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
            }

            $amountMinor = $money->amountMinor;
            $moneyCurrency = $money->currency;
        }

        $quantityName = StringNormalizer::optionalTrimmed($validated['quantityName'] ?? null);
        $quantityTotal = filled($validated['quantityTotal'] ?? null) ? (string) $validated['quantityTotal'] : null;
        $quantityUnit = StringNormalizer::optionalTrimmed($validated['quantityUnit'] ?? null);
        $doneCriteria = StringNormalizer::optionalTrimmed($validated['doneCriteria'] ?? null);

        if ($type === SubjectType::Quantity && ($quantityName === '' || $quantityTotal === null || $quantityUnit === '')) {
            throw ValidationException::withMessages(['subjectType' => 'Complete the quantity details.']);
        }

        if ($type === SubjectType::Quantity && QuantityValidator::isZero($quantityTotal)) {
            throw ValidationException::withMessages(['quantityTotal' => 'Enter a positive quantity.']);
        }

        if ($type === SubjectType::Commitment && $doneCriteria === '') {
            throw ValidationException::withMessages(['doneCriteria' => 'Describe what completion means.']);
        }

        return new self(
            $type,
            $amountMinor,
            $moneyCurrency,
            $quantityName,
            $quantityTotal,
            $quantityUnit,
            (bool) ($validated['isFractionable'] ?? false),
            $doneCriteria,
        );
    }
}
