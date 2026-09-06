<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;

final readonly class CompleteCommitmentData
{
    private function __construct(
        public ?string $note,
        public string $completedOn,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:4000'],
            'completedOn' => ['required', 'date'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input + ['completedOn' => today()->toDateString()], self::rules())->validate();
        $note = filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null;

        return new self($note, (string) $validated['completedOn']);
    }
}
