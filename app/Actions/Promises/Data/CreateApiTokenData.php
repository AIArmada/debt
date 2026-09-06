<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\ApiTokenAbility;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class CreateApiTokenData
{
    /**
     * @param  list<string>  $abilities
     */
    private function __construct(public string $name, public array $abilities) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::enum(ApiTokenAbility::class)],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        $abilities = array_values(array_unique(array_map(
            static fn (mixed $ability): string => ApiTokenAbility::from((string) $ability)->value,
            $validated['abilities'] ?? array_column(ApiTokenAbility::cases(), 'value'),
        )));

        return new self(StringNormalizer::trimmed((string) $validated['name']), $abilities);
    }
}
