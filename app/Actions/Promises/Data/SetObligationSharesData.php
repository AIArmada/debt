<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SetObligationSharesData
{
    /** @param list<array{partyId: string, shareBps: int}> $shares */
    private function __construct(public array $shares) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'shares' => ['present', 'array'],
            'shares.*.partyId' => ['required', 'uuid'],
            'shares.*.shareBps' => ['required', 'integer', 'between:1,10000'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{shares: list<array{partyId: string, shareBps: int|string}>} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $shares = array_map(static fn (array $share): array => [
            'partyId' => $share['partyId'],
            'shareBps' => (int) $share['shareBps'],
        ], $validated['shares']);

        $partyIds = array_column($shares, 'partyId');
        if (count($partyIds) !== count(array_unique($partyIds))) {
            throw ValidationException::withMessages(['shares' => 'Each party can only appear once.']);
        }

        return new self($shares);
    }
}
