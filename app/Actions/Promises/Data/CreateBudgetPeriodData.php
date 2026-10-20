<?php

namespace App\Actions\Promises\Data;

use App\Domain\Money\Currency;
use App\Domain\Money\Money;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreateBudgetPeriodData
{
    private function __construct(public string $startsOn, public string $endsOn, public int $incomeMinor, public int $essentialMinor, public int $reserveMinor, public string $currency) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'income' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'essentials' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'reserve' => ['required', 'string', 'regex:/^\d+(?:\.\d+)?$/'],
            'currency' => ['required', 'string', Rule::in(Currency::codes())],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();
        $currency = StringNormalizer::uppercase((string) $validated['currency']);
        $amounts = [];
        foreach (['income', 'essentials', 'reserve'] as $field) {
            try {
                $amounts[$field] = Money::parseMajor((string) $validated[$field], $currency)->amountMinor;
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([$field => $exception->getMessage()]);
            }
        }

        return new self((string) $validated['startsOn'], (string) $validated['endsOn'], $amounts['income'], $amounts['essentials'], $amounts['reserve'], $currency);
    }
}
