<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\MemberRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class InviteMemberData
{
    private function __construct(public string $email, public MemberRole $role) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(MemberRole::class)],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self(strtolower(trim((string) $validated['email'])), MemberRole::from((string) $validated['role']));
    }
}
