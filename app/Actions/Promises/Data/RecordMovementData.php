<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\MoneyEntry;
use App\Domain\Money\Money;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RecordMovementData
{
    private function __construct(
        public int $amountMinor,
        public string $currency,
        public ?MoneyEntry $entry,
        public string $occurredOn,
        public ?string $note,
        public bool $clampToOutstanding = false,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function settlementRules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function settlementFromInput(array $input, string $currency, string $occurredOn): self
    {
        /** @var array{amount:string, note:string|null} $validated */
        $validated = Validator::make($input, self::settlementRules())->validate();

        try {
            $money = Money::parseMajor($validated['amount'], $currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amount' => $exception->getMessage()]);
        }

        if ($money->amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount.']);
        }

        return self::settlement(
            $money->amountMinor,
            $money->currency,
            $occurredOn,
            StringNormalizer::optionalTrimmed($validated['note'] ?? null),
        );
    }

    public static function opening(int $amountMinor, string $currency, string $occurredOn, ?string $note = null): self
    {
        return new self($amountMinor, $currency, MoneyEntry::OpeningBalance, $occurredOn, $note);
    }

    public static function confirmed(
        int $amountMinor,
        string $currency,
        MoneyEntry $entry,
        string $occurredOn,
        ?string $note = null,
    ): self {
        return new self($amountMinor, $currency, $entry, $occurredOn, $note);
    }

    public static function settlement(
        int $amountMinor,
        string $currency,
        string $occurredOn,
        ?string $note = null,
    ): self {
        return new self($amountMinor, $currency, null, $occurredOn, $note, true);
    }
}
