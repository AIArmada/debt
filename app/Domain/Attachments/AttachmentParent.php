<?php

namespace App\Domain\Attachments;

use App\Models\Attachment;
use App\Models\FinancialProfile;
use App\Models\MoneyMovement;
use App\Models\Obligation;
use App\Models\QuantityReturn;
use App\Models\Record;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

final class AttachmentParent
{
    public function profile(Model $parent): FinancialProfile
    {
        return match (true) {
            $parent instanceof Record => $this->recordProfile($parent),
            $parent instanceof Obligation => $this->obligationProfile($parent),
            $parent instanceof MoneyMovement => $this->obligationProfile($parent->obligation),
            $parent instanceof QuantityReturn => $this->obligationProfile($parent->obligation),
            default => throw new LogicException('This model cannot have evidence attached.'),
        };
    }

    public function isSupported(Model $parent): bool
    {
        return $parent instanceof Record
            || $parent instanceof Obligation
            || $parent instanceof MoneyMovement
            || $parent instanceof QuantityReturn;
    }

    /**
     * @return MorphMany<Attachment, Record>|MorphMany<Attachment, Obligation>|MorphMany<Attachment, MoneyMovement>|MorphMany<Attachment, QuantityReturn>
     */
    public function attachments(Model $parent): MorphMany
    {
        return match (true) {
            $parent instanceof Record => $parent->attachments(),
            $parent instanceof Obligation => $parent->attachments(),
            $parent instanceof MoneyMovement => $parent->attachments(),
            $parent instanceof QuantityReturn => $parent->attachments(),
            default => throw new LogicException('This model cannot have evidence attached.'),
        };
    }

    public function profileForAttachment(Attachment $attachment): FinancialProfile
    {
        $parent = $attachment->attachable;

        if (! $parent instanceof Model) {
            throw new LogicException('The attachment parent no longer exists.');
        }

        return $this->profile($parent);
    }

    private function recordProfile(Record $record): FinancialProfile
    {
        $record->loadMissing('profile');

        return $record->profile;
    }

    private function obligationProfile(Obligation $obligation): FinancialProfile
    {
        $obligation->loadMissing('record.profile');

        return $obligation->record->profile;
    }
}
