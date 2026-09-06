<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\Direction;
use App\Domain\Enums\SubjectType;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class AddObligationData
{
    private function __construct(
        public Direction $direction,
        public ?string $dueOn,
        public ?string $note,
        public PromiseSubjectData $subject,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
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

        return new self(
            Direction::fromRequest($validated['direction']),
            $validated['dueOn'] ?? null,
            StringNormalizer::optionalTrimmed($validated['note'] ?? null),
            PromiseSubjectData::fromInput($validated, $currency),
        );
    }
}
