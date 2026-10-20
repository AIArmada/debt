<?php

namespace App\Actions\Promises\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;

final readonly class UpdateFinancialProfileData
{
    private function __construct(
        public string $name,
        public string $timezone,
        public bool $interestFreeMode,
        public bool $showHijri,
        public bool $interestFreeModeProvided,
        public bool $showHijriProvided,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'timezone'],
            'interestFreeMode' => ['sometimes', 'boolean'],
            'showHijri' => ['sometimes', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self(
            StringNormalizer::trimmed((string) $validated['name']),
            (string) $validated['timezone'],
            (bool) ($validated['interestFreeMode'] ?? true),
            (bool) ($validated['showHijri'] ?? false),
            array_key_exists('interestFreeMode', $validated),
            array_key_exists('showHijri', $validated),
        );
    }
}
