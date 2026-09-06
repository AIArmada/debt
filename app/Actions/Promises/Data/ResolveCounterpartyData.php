<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\PartyKind;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class ResolveCounterpartyData
{
    private function __construct(
        public string $name,
        public ?string $partyId,
        public PartyKind $kind,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'partyId' => ['nullable', 'uuid'],
            'kind' => ['required', Rule::enum(PartyKind::class)],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{name:string, partyId:string|null, kind:string} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $name = trim($validated['name']);

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter a person or organisation.']);
        }

        return new self(
            $name,
            $validated['partyId'] ?? null,
            PartyKind::from($validated['kind']),
        );
    }
}
