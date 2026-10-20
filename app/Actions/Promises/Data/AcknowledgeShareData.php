<?php

namespace App\Actions\Promises\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class AcknowledgeShareData
{
    /** @param array<string, string> $termsHashes */
    private function __construct(public string $name, public array $termsHashes) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'termsHashes' => ['sometimes', 'array'],
            'termsHashes.*' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{name: string, termsHashes?: array<string, string>} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $name = StringNormalizer::trimmed($validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter your name to acknowledge these terms.']);
        }

        return new self($name, $validated['termsHashes'] ?? []);
    }
}
