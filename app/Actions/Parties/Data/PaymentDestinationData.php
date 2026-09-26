<?php

namespace App\Actions\Parties\Data;

use App\Domain\Enums\PaymentDestinationKind;
use App\Domain\StringNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class PaymentDestinationData
{
    private function __construct(
        public PaymentDestinationKind $kind,
        public string $label,
        public string $details,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(PaymentDestinationKind::class)],
            'label' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'max:5000'],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        /** @var array{kind:string, label:string, details:string} $validated */
        $validated = Validator::make($input, self::rules())->validate();
        $label = StringNormalizer::trimmed($validated['label']);
        $details = StringNormalizer::trimmed($validated['details']);

        if ($label === '') {
            throw ValidationException::withMessages(['label' => 'Enter a label.']);
        }

        if ($details === '') {
            throw ValidationException::withMessages(['details' => 'Enter the destination details.']);
        }

        return new self(PaymentDestinationKind::from($validated['kind']), $label, $details);
    }
}
