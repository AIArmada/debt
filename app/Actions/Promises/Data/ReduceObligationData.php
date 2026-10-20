<?php

namespace App\Actions\Promises\Data;

use App\Domain\Money\Money;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class ReduceObligationData
{
    private function __construct(
        public ?int $amountMinor,
        public string $currency,
        public ?string $note,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'amount' => ['nullable', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input, string $currency): self
    {
        /** @var array{amount?: string|null, note?: string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $amount = $validated['amount'] ?? null;
        $amountMinor = null;

        if ($amount !== null && $amount !== '') {
            try {
                $amountMinor = Money::parseMajor($amount, $currency)->amountMinor;
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
            }

            if ($amountMinor <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
            }
        }

        return new self($amountMinor, StringNormalizer::uppercase($currency), StringNormalizer::optionalTrimmed($validated['note'] ?? null));
    }
}
