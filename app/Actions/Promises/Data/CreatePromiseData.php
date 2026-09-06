<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\Direction;
use App\Domain\Enums\SubjectType;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreatePromiseData
{
    private function __construct(
        public string $partyName,
        public ?string $partyId,
        public Direction $direction,
        public ?string $dueOn,
        public ?string $note,
        public PromiseSubjectData $subject,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'partyName' => ['required', 'string', 'max:255'],
            'partyId' => ['nullable', 'uuid'],
            'direction' => ['required', Rule::enum(Direction::class)],
            'dueOn' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:4000'],
            ...PromiseSubjectData::rules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, string $currency): self
    {
        $validated = Validator::make($input + ['subjectType' => SubjectType::Money->value], self::rules())->validate();
        $partyName = StringNormalizer::trimmed((string) $validated['partyName']);

        if ($partyName === '') {
            throw ValidationException::withMessages(['partyName' => 'Enter a person or organisation.']);
        }

        return new self(
            $partyName,
            $validated['partyId'] ?? null,
            Direction::fromRequest($validated['direction']),
            $validated['dueOn'] ?? null,
            StringNormalizer::optionalTrimmed($validated['note'] ?? null),
            PromiseSubjectData::fromInput($validated, $currency),
        );
    }
}
