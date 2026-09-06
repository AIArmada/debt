<?php

namespace App\Actions\Promises\Data;

use App\Domain\Money\Currency;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class SetExchangeRateData
{
    private function __construct(public string $from, public string $to, public string $rate, public string $ratedOn, public string $source) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'from' => ['required', 'string', Rule::in(Currency::codes())],
            'to' => ['required', 'string', Rule::in(Currency::codes())],
            'rate' => ['required', 'regex:/^\d+(?:\.\d{1,8})?$/'],
            'ratedOn' => ['required', 'date'],
            'source' => ['required', 'string', 'max:255'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();
        $from = strtoupper((string) $validated['from']);
        $to = strtoupper((string) $validated['to']);

        if ($from === $to) {
            throw ValidationException::withMessages(['to' => 'Choose a different target currency.']);
        }

        if (preg_match('/^0+(?:\.0{1,8})?$/', (string) $validated['rate']) === 1) {
            throw ValidationException::withMessages(['rate' => 'Enter a positive exchange rate.']);
        }

        return new self($from, $to, (string) $validated['rate'], (string) $validated['ratedOn'], trim((string) $validated['source']));
    }
}
