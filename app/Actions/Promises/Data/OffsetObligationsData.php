<?php

namespace App\Actions\Promises\Data;

use App\Domain\Money\Money;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class OffsetObligationsData
{
    private function __construct(
        public string $payableObligationId,
        public string $receivableObligationId,
        public int $amountMinor,
        public string $currency,
        public string $occurredOn,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'payableObligationId' => ['required', 'uuid'],
            'receivableObligationId' => ['required', 'uuid', 'different:payableObligationId'],
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'currency' => ['required', 'string', 'size:3'],
            'occurredOn' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{payableObligationId: string, receivableObligationId: string, amount: string, currency: string, occurredOn: string} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $currency = StringNormalizer::uppercase($validated['currency']);

        try {
            $money = Money::parseMajor($validated['amount'], $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        if ($money->amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        }

        return new self(
            $validated['payableObligationId'],
            $validated['receivableObligationId'],
            $money->amountMinor,
            $money->currency,
            $validated['occurredOn'],
        );
    }
}
