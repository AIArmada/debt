<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\Direction;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Enums\SubjectType;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class CreatePromiseData
{
    private function __construct(
        public string $partyName,
        public ?string $purpose,
        public ?string $partyId,
        public Direction $direction,
        public ?string $dueOn,
        public ?string $note,
        public PromiseSubjectData $subject,
        public RecordVisibility $visibility,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'partyName' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'partyId' => ['nullable', 'uuid'],
            'direction' => ['required', Rule::enum(Direction::class)],
            'dueOn' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:4000'],
            'visibility' => ['required', Rule::enum(RecordVisibility::class)],
            ...PromiseSubjectData::rules(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input, string $currency): self
    {
        $validated = Validator::make($input + [
            'subjectType' => SubjectType::Money->value,
            'visibility' => RecordVisibility::Shared->value,
        ], self::rules())->validate();
        $partyName = StringNormalizer::trimmed((string) $validated['partyName']);
        $purpose = StringNormalizer::optionalTrimmed($validated['purpose'] ?? null);

        if ($partyName === '') {
            throw ValidationException::withMessages(['partyName' => 'Enter a person or organisation.']);
        }

        return new self(
            $partyName,
            $purpose,
            $validated['partyId'] ?? null,
            Direction::fromRequest($validated['direction']),
            $validated['dueOn'] ?? null,
            StringNormalizer::optionalTrimmed($validated['note'] ?? null),
            PromiseSubjectData::fromInput($validated, $currency),
            RecordVisibility::from((string) $validated['visibility']),
        );
    }
}
