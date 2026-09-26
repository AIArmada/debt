<?php

namespace App\Actions\Parties\Data;

use App\Domain\Enums\PartyRelationshipKind;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class PartyRelationshipData
{
    private function __construct(
        public string $toPartyId,
        public PartyRelationshipKind $kind,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'toPartyId' => ['required', 'uuid'],
            'kind' => ['required', Rule::enum(PartyRelationshipKind::class)],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{toPartyId:string, kind:string} $validated */
        $validated = Validator::make($input, self::rules())->validate();

        return new self($validated['toPartyId'], PartyRelationshipKind::from($validated['kind']));
    }
}
