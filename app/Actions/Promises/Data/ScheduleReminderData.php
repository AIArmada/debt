<?php

namespace App\Actions\Promises\Data;

use App\Domain\Enums\ReminderChannel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class ScheduleReminderData
{
    private function __construct(public string $remindOn, public ReminderChannel $channel) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'remindOn' => ['required', 'date'],
            'channel' => ['required', Rule::enum(ReminderChannel::class)],
        ];
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $input += ['channel' => ReminderChannel::Database->value];
        $validated = Validator::make($input, self::rules())->validate();

        return new self((string) $validated['remindOn'], ReminderChannel::from((string) $validated['channel']));
    }
}
