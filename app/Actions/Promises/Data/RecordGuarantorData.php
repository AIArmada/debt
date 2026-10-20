<?php

namespace App\Actions\Promises\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RecordGuarantorData
{
    private function __construct(public ?string $partyId, public ?string $partyName) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'partyId' => ['nullable', 'uuid', 'required_without:partyName'],
            'partyName' => ['nullable', 'string', 'max:255', 'required_without:partyId'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{partyId?: string|null, partyName?: string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $partyId = $validated['partyId'] ?? null;
        $partyName = StringNormalizer::optionalTrimmed($validated['partyName'] ?? null);

        if ($partyId === null && ($partyName === null || $partyName === '')) {
            throw ValidationException::withMessages(['partyName' => 'Choose an existing party or enter a name.']);
        }

        return new self($partyId, $partyName);
    }
}
