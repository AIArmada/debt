<?php

namespace App\Actions\Parties\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class PartyContactData
{
    private function __construct(
        public string $label,
        public string $value,
        public bool $isPrimary,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'isPrimary' => ['boolean'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{label:string, value:string, isPrimary:bool|int|string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $label = StringNormalizer::trimmed($validated['label']);
        $value = StringNormalizer::trimmed($validated['value']);

        if ($label === '') {
            throw ValidationException::withMessages(['label' => 'Enter a label.']);
        }

        if ($value === '') {
            throw ValidationException::withMessages(['value' => 'Enter a contact value.']);
        }

        return new self($label, $value, (bool) ($validated['isPrimary'] ?? false));
    }
}
