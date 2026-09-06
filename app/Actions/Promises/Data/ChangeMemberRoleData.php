<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\MemberRole;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ChangeMemberRoleData
{
    private function __construct(public MemberRole $role) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return ['role' => ['required', Rule::enum(MemberRole::class)]];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->validate();

        return new self(MemberRole::from((string) $validated['role']));
    }
}
