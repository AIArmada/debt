<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

final readonly class CreateRecordShareData
{
    private function __construct(public ?Carbon $expiresAt) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['expiresAt' => ['nullable', 'date', 'after:now']];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{expiresAt?: string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();

        return new self(isset($validated['expiresAt']) ? Carbon::parse($validated['expiresAt']) : null);
    }
}
