<?php

namespace App\Actions\Promises\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateFinancialProfileData
{
    private function __construct(public string $name, public string $timezone) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self(StringNormalizer::trimmed((string) $validated['name']), (string) $validated['timezone']);
    }
}
