<?php

namespace App\Policies;

use App\Domain\Attachments\AttachmentParent;
use App\Domain\Enums\MemberRole;
use App\Models\Attachment;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Database\Eloquent\Model;

final class AttachmentPolicy
{
    public function __construct(
        private readonly AttachmentParent $attachmentParent,
        private readonly ProfileAccess $profileAccess,
    ) {}

    public function create(User $user, Model $parent): bool
    {
        if (! $this->attachmentParent->isSupported($parent)) {
            return false;
        }

        return $this->profileAccess->can($user, $this->attachmentParent->profile($parent), [MemberRole::Owner, MemberRole::Editor]);
    }

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->profileAccess->can($user, $this->attachmentParent->profileForAttachment($attachment), [
            MemberRole::Owner,
            MemberRole::Editor,
            MemberRole::Viewer,
        ]);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $this->profileAccess->can($user, $this->attachmentParent->profileForAttachment($attachment), [
            MemberRole::Owner,
            MemberRole::Editor,
        ]);
    }
}
