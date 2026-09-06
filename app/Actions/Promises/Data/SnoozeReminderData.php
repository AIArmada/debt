<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;

final readonly class SnoozeReminderData
{
    private function __construct(public string $until) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['until' => ['required', 'date']];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self((string) $validated['until']);
    }
}
