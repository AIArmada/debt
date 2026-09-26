<?php

namespace App\Domain\Enums;

enum PartyRelationshipKind: string
{
    case HouseholdMember = 'household_member';
    case AssistantOf = 'assistant_of';
    case RepresentativeOf = 'representative_of';
    case RelativeOf = 'relative_of';
    case ColleagueOf = 'colleague_of';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HouseholdMember => 'household member',
            self::AssistantOf => 'assistant of',
            self::RepresentativeOf => 'representative of',
            self::RelativeOf => 'relative of',
            self::ColleagueOf => 'colleague of',
            self::Other => 'other',
        };
    }
}
