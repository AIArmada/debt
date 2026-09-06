<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CorrectQuantityReturnData
{
    private function __construct(
        public string $quantity,
        public string $returnedOn,
        public ?string $note,
        public string $reason,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'returnedOn' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:4000'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        /** @var array{quantity:string, returnedOn:string, note:string|null, reason:string} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $quantity = $validated['quantity'];
        $reason = trim($validated['reason']);

        if (preg_match('/^0+(?:\.0{1,4})?$/', $quantity) === 1) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive quantity.']);
        }

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a reason for correcting this return.']);
        }

        return new self(
            $quantity,
            $validated['returnedOn'],
            filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            $reason,
        );
    }
}
