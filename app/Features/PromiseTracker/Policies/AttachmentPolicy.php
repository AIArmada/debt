<?php

namespace App\Features\PromiseTracker\Policies;

use App\Features\PromiseTracker\Domain\Attachments\AttachmentParent;
use App\Features\PromiseTracker\Domain\Enums\MemberRole;
use App\Features\PromiseTracker\Models\Attachment;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\MoneyMovement;
use App\Features\PromiseTracker\Models\Obligation;
use App\Features\PromiseTracker\Models\QuantityReturn;
use App\Features\PromiseTracker\Models\Record;
use App\Features\PromiseTracker\Models\User;
use App\Features\PromiseTracker\Services\ProfileAccess;
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
