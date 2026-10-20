<?php

namespace App\Policies;

use App\Domain\Attachments\AttachmentParent;
use App\Domain\Enums\MemberRole;
use App\Models\Attachment;
use App\Models\FinancialProfile;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\QuantityReturn;
use App\Models\Record;
use App\Models\User;
use App\Services\ProfileAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

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

        return $this->profileAccess->can($user, $this->attachmentParent->profile($parent), [MemberRole::Owner, MemberRole::Editor])
            && Gate::forUser($user)->allows('update', $this->record($parent));
    }

    public function view(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;
        if ($parent instanceof FinancialProfile) {
            return $this->profileAccess->can($user, $parent, [
                MemberRole::Owner,
                MemberRole::Editor,
                MemberRole::Viewer,
            ]);
        }
        if (! $parent instanceof Model || ! $this->attachmentParent->isSupported($parent)) {
            return false;
        }

        return $this->profileAccess->can($user, $this->attachmentParent->profile($parent), [
            MemberRole::Owner,
            MemberRole::Editor,
            MemberRole::Viewer,
        ]) && Gate::forUser($user)->allows('view', $this->record($parent));
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $parent = $attachment->attachable;
        if ($parent instanceof FinancialProfile) {
            return $this->profileAccess->can($user, $parent, [
                MemberRole::Owner,
                MemberRole::Editor,
            ]);
        }
        if (! $parent instanceof Model || ! $this->attachmentParent->isSupported($parent)) {
            return false;
        }

        return $this->profileAccess->can($user, $this->attachmentParent->profile($parent), [
            MemberRole::Owner,
            MemberRole::Editor,
        ]) && Gate::forUser($user)->allows('update', $this->record($parent));
    }

    private function record(?Model $parent): Record
    {
        return match (true) {
            $parent instanceof Record => $parent,
            $parent instanceof Obligation => $parent->record,
            $parent instanceof MoneyMovement => $parent->obligation->record,
            $parent instanceof QuantityReturn => $parent->obligation->record,
            default => throw new \LogicException('The attachment does not belong to a promise record.'),
        };
    }
}
