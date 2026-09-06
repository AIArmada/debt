<?php

namespace App\Livewire\Promises;

use App\Actions\Promises\AddObligation;
use App\Actions\Promises\ArchiveRecord;
use App\Actions\Promises\AttachEvidence;
use App\Actions\Promises\CompleteCommitment;
use App\Actions\Promises\Data\AddObligationData;
use App\Actions\Promises\Data\AttachEvidenceData;
use App\Actions\Promises\Data\CompleteCommitmentData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\Data\RecordQuantityReturnData;
use App\Actions\Promises\Data\ReopenCommitmentData;
use App\Actions\Promises\Data\SaveNoteData;
use App\Actions\Promises\Data\ScheduleReminderData;
use App\Actions\Promises\Data\SettleObligationData;
use App\Actions\Promises\Data\SnoozeReminderData;
use App\Actions\Promises\Data\UpdatePromiseDetailsData;
use App\Actions\Promises\Data\VoidMovementData;
use App\Actions\Promises\DeleteRecord;
use App\Actions\Promises\DetachEvidence;
use App\Actions\Promises\DismissReminder;
use App\Actions\Promises\RecordMoneyMovement;
use App\Actions\Promises\ReopenCommitment;
use App\Actions\Promises\RestoreRecord;
use App\Actions\Promises\ReturnQuantity;
use App\Actions\Promises\SaveNote;
use App\Actions\Promises\ScheduleReminder;
use App\Actions\Promises\SettleObligation;
use App\Actions\Promises\SnoozeReminder;
use App\Actions\Promises\UpdatePromiseDetails;
use App\Actions\Promises\VoidMovement;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\Direction;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Domain\Queries\OutstandingQuantity;
use App\Domain\Queries\PromiseShowData;
use App\Domain\Queries\RecordActivity;
use App\Domain\Queries\RecordStatusQuery;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class Show extends Component
{
    use WithFileUploads;

    public FinancialProfile $profile;

    public Record $record;

    public bool $editingDetails = false;

    public string $promiseTitle = '';

    public ?string $promiseDueOn = null;

    public string $partialAmount = '';

    public string $movementNote = '';

    public string $recordNote = '';

    public string $quantityReturn = '';

    public string $quantityReturnedOn = '';

    public string $completionNote = '';

    public string $reopenReason = '';

    public string $partDirection = Direction::Payable->value;

    public string $partSubjectType = SubjectType::Money->value;

    public string $partAmount = '';

    public string $partQuantityName = '';

    public string $partQuantityTotal = '';

    public string $partQuantityUnit = '';

    public bool $partIsFractionable = false;

    public string $partDoneCriteria = '';

    public ?string $partDueOn = null;

    public string $partNote = '';

    public string $reminderDate = '';

    public string $voidReason = 'Recorded in error';

    public ?TemporaryUploadedFile $evidenceFile = null;

    public string $evidenceLinkUrl = '';

    public string $evidenceCategory = AttachmentCategory::Other->value;

    public ?string $evidenceMovementId = null;

    public ?string $evidenceReturnId = null;

    public function mount(FinancialProfile $profile, Record $record): void
    {
        $this->profile = $profile;
        $this->record = $record;
        $this->recordNote = (string) ($record->note ?? '');
        $this->promiseTitle = $record->title;
        $this->promiseDueOn = $record->obligations()->oldest('created_at')->oldest('id')->value('due_on');
        $this->quantityReturnedOn = today()->toDateString();
        $this->reminderDate = today()->addDay()->toDateString();
        Gate::authorize('view', $record);
    }

    public function saveNote(SaveNote $saveNote): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $saveNote->handle($user, $this->record, SaveNoteData::fromInput([
            'note' => $this->recordNote,
        ]));
    }

    public function beginEditingDetails(): void
    {
        Gate::authorize('update', $this->record);

        if ($this->record->hasConfirmedMovements()) {
            return;
        }

        $this->promiseTitle = $this->record->title;
        $this->promiseDueOn = $this->record->obligations()->oldest('created_at')->oldest('id')->value('due_on');
        $this->editingDetails = true;
    }

    public function updateDetails(UpdatePromiseDetails $updatePromiseDetails): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $updatePromiseDetails->handle($user, $this->record, UpdatePromiseDetailsData::fromInput([
            'recordId' => $this->record->getKey(),
            'obligationId' => $this->record->obligations()->oldest('created_at')->oldest('id')->value('id'),
            'title' => $this->promiseTitle,
            'dueOn' => $this->promiseDueOn,
        ]));
        $this->editingDetails = false;
        $this->record->load('obligations');
    }

    public function delete(DeleteRecord $deleteRecord): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $deleteRecord->handle($user, $this->record);
        $this->redirectRoute('promises.index', ['profile' => $this->profile], navigate: true);
    }

    public function restart(DeleteRecord $deleteRecord, PromiseShowData $promiseShowData): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $query = $promiseShowData->restartQuery($this->record);
        $deleteRecord->handle($user, $this->record);
        $this->redirectRoute('promises.create', ['profile' => $this->profile] + $query, navigate: true);
    }

    public function archive(ArchiveRecord $archiveRecord): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $archiveRecord->handle($user, $this->record);
    }

    public function restore(RestoreRecord $restoreRecord): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->record = $restoreRecord->handle($user, $this->record);
    }

    public function prepareRecordEvidence(): void
    {
        $this->evidenceMovementId = null;
        $this->evidenceReturnId = null;
    }

    public function prepareMovementEvidence(string $movementId): void
    {
        $this->evidenceMovementId = $movementId;
        $this->evidenceReturnId = null;
    }

    public function prepareReturnEvidence(string $returnId): void
    {
        $this->evidenceMovementId = null;
        $this->evidenceReturnId = $returnId;
    }

    public function saveEvidence(AttachEvidence $attachEvidence, PromiseShowData $promiseShowData): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $attachEvidence->handle($user, $promiseShowData->evidenceParent($this->record, $this->evidenceMovementId, $this->evidenceReturnId), AttachEvidenceData::fromInput([
            'linkUrl' => $this->evidenceLinkUrl,
            'category' => $this->evidenceCategory,
        ], $this->evidenceFile));
        $this->reset(['evidenceFile', 'evidenceLinkUrl']);
        $this->evidenceCategory = AttachmentCategory::Other->value;
        $this->prepareRecordEvidence();
        $this->record->refresh();
    }

    public function detachEvidence(string $attachmentId, DetachEvidence $detachEvidence): void
    {
        $attachment = $this->profile->attachments()->whereKey($attachmentId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $detachEvidence->handle($user, $attachment);
        $this->record->refresh();
    }

    public function recordPartial(RecordMoneyMovement $recordMovement, OutstandingBalance $outstandingBalance): void
    {
        $obligation = $this->moneyObligation();
        $currency = (string) $obligation->moneySubject->currency;

        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $data = RecordMovementData::settlementFromInput([
            'amount' => $this->partialAmount,
            'note' => $this->movementNote,
        ], $currency, today()->toDateString());
        $recordMovement->handle($user, $obligation, $data);
        $outstandingBalance->forget($obligation);
        $this->partialAmount = '';
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function recordQuantityReturn(ReturnQuantity $returnQuantity): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $returnQuantity->handle($user, $this->quantityObligation(), RecordQuantityReturnData::fromInput([
            'quantity' => $this->quantityReturn,
            'returnedOn' => $this->quantityReturnedOn,
            'note' => $this->movementNote,
        ]));
        $this->quantityReturn = '';
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function markSettled(SettleObligation $settleObligation): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $settleObligation->handle($user, $this->moneyObligation(), SettleObligationData::fromInput([
            'note' => $this->movementNote,
        ]));
        $this->movementNote = '';
        $this->record->refresh();
    }

    public function completeCommitment(CompleteCommitment $completeCommitment): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $completeCommitment->handle($user, $this->commitmentObligation(), CompleteCommitmentData::fromInput([
            'note' => $this->completionNote,
        ]));
        $this->completionNote = '';
        $this->record->refresh();
    }

    public function reopenCommitment(ReopenCommitment $reopenCommitment): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $reopenCommitment->handle($user, $this->commitmentObligation(), ReopenCommitmentData::fromInput([
            'reason' => $this->reopenReason,
        ]));
        $this->reopenReason = '';
        $this->record->refresh();
    }

    public function addObligation(AddObligation $addObligation): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $addObligation->handle($user, $this->record, AddObligationData::fromInput([
            'direction' => $this->partDirection,
            'subjectType' => $this->partSubjectType,
            'amount' => $this->partAmount,
            'quantityName' => $this->partQuantityName,
            'quantityTotal' => $this->partQuantityTotal,
            'quantityUnit' => $this->partQuantityUnit,
            'isFractionable' => $this->partIsFractionable,
            'doneCriteria' => $this->partDoneCriteria,
            'dueOn' => $this->partDueOn,
            'note' => $this->partNote,
        ], (string) $this->profile->base_currency));
        $this->reset([
            'partAmount', 'partQuantityName', 'partQuantityTotal', 'partQuantityUnit',
            'partDoneCriteria', 'partDueOn', 'partNote',
        ]);
        $this->partDirection = Direction::Payable->value;
        $this->partSubjectType = SubjectType::Money->value;
        $this->partIsFractionable = false;
        $this->record->refresh();
    }

    public function scheduleReminder(ScheduleReminder $scheduleReminder): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $scheduleReminder->handle($user, $this->primaryObligation(), ScheduleReminderData::fromInput(['remindOn' => $this->reminderDate]));
        $this->record->refresh();
    }

    public function dismissReminder(string $reminderId, DismissReminder $dismissReminder): void
    {
        $reminder = $this->primaryObligation()->reminders()->whereKey($reminderId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $dismissReminder->handle($user, $reminder);
        $this->record->refresh();
    }

    public function snoozeReminder(string $reminderId, SnoozeReminder $snoozeReminder): void
    {
        $reminder = $this->primaryObligation()->reminders()->whereKey($reminderId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $snoozeReminder->handle($user, $reminder, SnoozeReminderData::fromInput(['until' => $this->reminderDate]));
        $this->record->refresh();
    }

    public function voidMovement(string $movementId, VoidMovement $voidMovement): void
    {
        $movement = $this->moneyObligation()->moneyMovements()->whereKey($movementId)->firstOrFail();
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $voidMovement->handle($user, $movement, VoidMovementData::fromInput(['reason' => $this->voidReason]));
        $this->voidReason = 'Recorded in error';
        $this->record->refresh();
    }

    public function render(OutstandingBalance $outstandingBalance, OutstandingQuantity $outstandingQuantity, PromiseShowData $promiseShowData, RecordActivity $recordActivity, RecordStatusQuery $recordStatus): View
    {
        $obligations = $promiseShowData->obligations($this->record);
        $this->record->loadMissing('attachments');
        $obligation = $obligations->firstOrFail();
        $balances = $obligation->subject_type === SubjectType::Money
            ? $outstandingBalance->forObligation($obligation)
            : [];
        $quantityPosition = $obligation->subject_type === SubjectType::Quantity
            ? $outstandingQuantity->forObligation($obligation)
            : null;
        $positions = $obligation->subject_type === SubjectType::Money
            ? $promiseShowData->positions($obligation, $balances)
            : [];

        return view('livewire.promises.show', [
            'obligation' => $obligation,
            'positions' => $positions,
            'quantityPosition' => $quantityPosition,
            'obligations' => $obligations,
            'movements' => $obligation->moneyMovements,
            'returns' => $obligation->quantityReturns,
            'activities' => $recordActivity->forRecord($this->record),
            'recordStatus' => $recordStatus->forRecord($this->record),
            'canDeleteRecord' => $this->record->canBeDeleted(),
        ])->layout('layouts.app', ['title' => $this->record->title]);
    }

    private function moneyObligation(): Obligation
    {
        return $this->record->obligations()
            ->where('subject_type', SubjectType::Money->value)
            ->with('moneySubject')
            ->firstOrFail();
    }

    private function primaryObligation(): Obligation
    {
        return $this->record->obligations()->firstOrFail();
    }

    private function quantityObligation(): Obligation
    {
        return $this->record->obligations()
            ->where('subject_type', SubjectType::Quantity->value)
            ->with('quantitySubject')
            ->firstOrFail();
    }

    private function commitmentObligation(): Obligation
    {
        return $this->record->obligations()
            ->where('subject_type', SubjectType::Commitment->value)
            ->with('commitmentSubject')
            ->firstOrFail();
    }
}
