<?php

namespace App\Domain\Enums;

enum MemberRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canWrite(): bool
    {
        return in_array($this, [self::Owner, self::Editor], true);
    }
}
