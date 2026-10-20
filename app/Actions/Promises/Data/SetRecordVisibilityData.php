<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\RecordVisibility;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SetRecordVisibilityData
{
    private function __construct(public RecordVisibility $visibility) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return ['visibility' => ['required', Rule::enum(RecordVisibility::class)]];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self(RecordVisibility::from((string) $validated['visibility']));
    }
}
