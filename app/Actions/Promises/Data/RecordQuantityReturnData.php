<?php

namespace App\Actions\Promises\Data;

use App\Domain\Quantities\QuantityValidator;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class RecordQuantityReturnData
{
    private function __construct(
        public string $quantity,
        public string $returnedOn,
        public ?string $note,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'quantity' => ['required', 'string', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'returnedOn' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        /** @var array{quantity:string, returnedOn:string, note:string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();

        if (QuantityValidator::isZero($validated['quantity'])) {
            throw ValidationException::withMessages(['quantity' => 'Enter a positive quantity.']);
        }

        return new self(
            $validated['quantity'],
            $validated['returnedOn'],
            StringNormalizer::optionalTrimmed($validated['note'] ?? null),
        );
    }
}
