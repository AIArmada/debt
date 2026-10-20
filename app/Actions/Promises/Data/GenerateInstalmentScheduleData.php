<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class GenerateInstalmentScheduleData
{
    private function __construct(
        public int $count,
        public string $frequency,
        public ?string $firstDueOn,
        public ?int $payDayOfMonth,
    ) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        if (($input['firstDueOn'] ?? null) === '') {
            $input['firstDueOn'] = null;
        }
        if (($input['payDayOfMonth'] ?? null) === '') {
            $input['payDayOfMonth'] = null;
        }

        $validated = Validator::make($input, [
            'count' => ['required', 'integer', 'min:1', 'max:600'],
            'frequency' => ['required', 'string', 'in:weekly,monthly'],
            'firstDueOn' => ['nullable', 'date'],
            'payDayOfMonth' => ['nullable', 'integer', 'min:1', 'max:28'],
        ])->validate();

        $hasFirstDue = $validated['firstDueOn'] !== null;
        $hasPayDay = $validated['payDayOfMonth'] !== null;
        if ($hasFirstDue === $hasPayDay || ($hasPayDay && $validated['frequency'] !== 'monthly')) {
            throw ValidationException::withMessages([
                'firstDueOn' => 'Choose a first due date or a monthly pay day from 1 to 28.',
            ]);
        }

        return new self(
            (int) $validated['count'],
            (string) $validated['frequency'],
            $hasFirstDue ? (string) $validated['firstDueOn'] : null,
            $hasPayDay ? (int) $validated['payDayOfMonth'] : null,
        );
    }
}
