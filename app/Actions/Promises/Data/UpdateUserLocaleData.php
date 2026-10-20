<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;

final readonly class UpdateUserLocaleData
{
    private function __construct(public string $locale) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['locale' => ['required', 'string', 'in:en,ms']];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{locale: string} $validated */
        $validated = Validator::make($input, self::rules())->validate();

        return new self($validated['locale']);
    }
}
