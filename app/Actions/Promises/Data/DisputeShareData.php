<?php

namespace App\Actions\Promises\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class DisputeShareData
{
    private function __construct(public string $name, public ?string $note) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{name: string, note?: string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $name = StringNormalizer::trimmed($validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter your name to dispute this promise.']);
        }

        return new self($name, StringNormalizer::optionalTrimmed($validated['note'] ?? null));
    }
}
