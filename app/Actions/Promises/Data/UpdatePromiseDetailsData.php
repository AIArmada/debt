<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class UpdatePromiseDetailsData
{
    private function __construct(
        public string $recordId,
        public ?string $obligationId,
        public string $title,
        public ?string $dueOn,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'recordId' => ['required', 'uuid'],
            'obligationId' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'dueOn' => ['nullable', 'date'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $input = [
            'recordId' => $input['recordId'] ?? $input['record_id'] ?? null,
            'obligationId' => $input['obligationId'] ?? $input['obligation_id'] ?? null,
            'title' => $input['title'] ?? null,
            'dueOn' => $input['dueOn'] ?? $input['due_on'] ?? null,
        ];
        $validated = Validator::make($input, self::rules())->validate();
        $title = trim((string) $validated['title']);

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Enter a promise title.']);
        }

        return new self(
            (string) $validated['recordId'],
            $validated['obligationId'] !== null ? (string) $validated['obligationId'] : null,
            $title,
            $validated['dueOn'] !== null ? (string) $validated['dueOn'] : null,
        );
    }
}
