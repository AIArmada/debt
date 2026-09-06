<?php

namespace App\Actions\Promises\Data;

use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;

final readonly class SaveNoteData
{
    private function __construct(public ?string $note) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:4000']];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        /** @var array{note:string|null} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $note = StringNormalizer::optionalTrimmed($validated['note'] ?? null);

        return new self($note);
    }
}
