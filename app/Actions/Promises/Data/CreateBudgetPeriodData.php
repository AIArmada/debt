<?php

namespace App\Actions\Promises\Data;

use App\Domain\Money\Currency;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CreateBudgetPeriodData
{
    private function __construct(public string $startsOn, public string $endsOn, public int $incomeMinor, public int $essentialMinor, public int $reserveMinor, public string $currency) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'incomeMinor' => ['required', 'integer', 'min:0'],
            'essentialMinor' => ['required', 'integer', 'min:0'],
            'reserveMinor' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', Rule::in(Currency::codes())],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self((string) $validated['startsOn'], (string) $validated['endsOn'], (int) $validated['incomeMinor'], (int) $validated['essentialMinor'], (int) $validated['reserveMinor'], strtoupper((string) $validated['currency']));
    }
}
