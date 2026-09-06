<?php

namespace App\Actions\Promises\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class VoidMovementData
{
    private function __construct(public string $reason) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        /** @var array{reason:string} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $reason = trim($validated['reason']);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a reason for voiding this movement.']);
        }

        return new self($reason);
    }
}
