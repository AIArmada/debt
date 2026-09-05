<?php

namespace App\Livewire\Obligations;

use App\Actions\Obligations\UpdateObligation;
use App\Domain\Money\MoneyAmount;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\Obligation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Edit extends Component
{
    public Obligation $obligation;

    public string $obligationKind = 'money';

    #[Validate('required|in:snapshot,ledger')]
    public string $trackingMode = 'snapshot';

    public bool $hasTransactions = false;

    #[Validate('required|string|max:100')]
    public string $title = '';

    #[Validate('required|string|max:60')]
    public string $category = 'personal_loan';

    #[Validate('nullable|numeric|min:0')]
    public ?string $originalAmount = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $currentPrincipalBalance = null;

    #[Validate('nullable|numeric')]
    public ?string $currentTotalBalance = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $minimumPaymentAmount = null;

    #[Validate('nullable|date')]
    public ?string $startedOn = null;

    #[Validate('nullable|date')]
    public ?string $dueOn = null;

    #[Validate('nullable|date')]
    public ?string $nextDueOn = null;

    #[Validate('required|in:verified,partial,estimated')]
    public string $dataConfidence = 'partial';

    #[Validate('boolean')]
    public bool $isInterestBearing = false;

    #[Validate('nullable|string|max:4000')]
    public string $description = '';

    #[Validate('nullable|string|max:255')]
    public string $subjectName = '';

    #[Validate('nullable|numeric|min:0.0001')]
    public ?string $subjectQuantity = null;

    #[Validate('required|in:countable,measurable')]
    public string $quantityMode = 'countable';

    public ?string $currentSubjectQuantity = null;

    public bool $quantityNeedsReconciliation = false;

    #[Validate('nullable|string|max:60')]
    public string $subjectUnit = 'item';

    #[Validate('nullable|string|max:60')]
    public string $subjectCondition = '';

    #[Validate('nullable|string|max:4000')]
    public string $subjectDetails = '';

    #[Validate('nullable|string|max:40')]
    public string $assetType = 'physical';

    #[Validate('nullable|string|max:40')]
    public string $serviceType = 'time';

    #[Validate('nullable|numeric|min:0')]
    public ?string $estimatedValue = null;

    #[Validate('nullable|string|size:3')]
    public ?string $estimatedValueCurrency = null;

    #[Validate('nullable|string|max:4000')]
    public string $completionCriteria = '';

    #[Validate('boolean')]
    public bool $isConditional = false;

    #[Validate('nullable|string|max:4000')]
    public string $conditionDescription = '';

    #[Validate('nullable|date')]
    public ?string $conditionTriggeredOn = null;

    public function mount(Obligation $obligation): void
    {
        Gate::authorize('update', $obligation);
        $this->obligation = $obligation->load('record');
        $kind = $obligation->kind();
        $this->obligationKind = $kind->value;
        $this->trackingMode = $obligation->tracking_mode ?? 'snapshot';
        $this->hasTransactions = $obligation->transactions()->exists();
        $this->title = $obligation->title;
        $this->category = $obligation->category;
        $this->originalAmount = $this->moneyAttribute($obligation, 'original_amount', (string) $obligation->currency);
        $this->currentPrincipalBalance = $this->moneyAttribute($obligation, 'current_principal_balance', (string) $obligation->currency);
        $this->currentTotalBalance = $this->moneyAttribute($obligation, 'current_total_balance', (string) $obligation->currency);
        $this->minimumPaymentAmount = $this->moneyAttribute($obligation, 'minimum_payment_amount', (string) $obligation->currency);
        $this->startedOn = $this->dateAttribute($obligation, 'started_on');
        $this->dueOn = $this->dateAttribute($obligation, 'due_on');
        $this->nextDueOn = $this->dateAttribute($obligation, 'next_due_on');
        $this->dataConfidence = $obligation->data_confidence;
        $this->isInterestBearing = $obligation->is_interest_bearing;
        $this->description = $obligation->description ?? '';
        $this->subjectName = $obligation->subject_name ?? '';
        $this->subjectQuantity = $this->stringAttribute($obligation, 'subject_quantity');
        $this->quantityMode = $obligation->quantityMode()->value;
        $this->currentSubjectQuantity = $this->stringAttribute($obligation, 'current_subject_quantity');
        $this->quantityNeedsReconciliation = $obligation->quantityNeedsReview();
        $this->subjectUnit = $obligation->subject_unit ?? ($kind === ObligationKind::Service ? 'hour' : 'item');
        $this->subjectCondition = $obligation->subject_condition ?? '';
        $this->subjectDetails = $obligation->subject_details ?? '';
        $this->assetType = $obligation->asset_type ?? 'physical';
        $this->serviceType = $obligation->service_type ?? 'time';
        $this->estimatedValueCurrency = $obligation->estimated_value_currency;
        $this->estimatedValue = $obligation->estimated_value_currency === null
            ? null
            : $this->moneyAttribute($obligation, 'estimated_value', (string) $obligation->estimated_value_currency);
        $this->completionCriteria = $obligation->completion_criteria ?? '';
        $this->isConditional = $obligation->is_conditional ?? false;
        $this->conditionDescription = $obligation->condition_description ?? '';
        $this->conditionTriggeredOn = $this->dateAttribute($obligation, 'condition_triggered_on');
    }

    public function save(UpdateObligation $updateObligation): void
    {
        Gate::authorize('update', $this->obligation);
        $validated = $this->validate();
        $kind = $this->obligation->kind();

        if (! array_key_exists($validated['category'], $kind->categoryOptions())) {
            $this->addError('category', 'Choose a category that matches this obligation type.');
        }

        if ($kind->isMoney() && $this->trackingMode === 'snapshot' && $this->obligation->transactions()->exists()) {
            $this->addError('trackingMode', 'This record has movement history, so it must remain on detailed ledger tracking.');
        }

        if ($kind->isMoney() && $this->trackingMode === 'snapshot' && blank($validated['currentTotalBalance'])) {
            $this->addError('currentTotalBalance', 'Enter the current balance for snapshot tracking.');
        }

        if ($kind->isMoney() && $this->trackingMode === 'snapshot' && filled($validated['currentTotalBalance'])) {
            try {
                $snapshotBalance = MoneyAmount::fromMajor($validated['currentTotalBalance'], (string) $this->obligation->currency);
                if (($snapshotBalance ?? 0) < 0) {
                    $this->addError('currentTotalBalance', 'A manual snapshot balance cannot be below zero. Use ledger movements to record a reversed position.');
                }
            } catch (\InvalidArgumentException $exception) {
                $this->addError('currentTotalBalance', $exception->getMessage());
            }
        }

        if ($kind === ObligationKind::Asset && blank($validated['subjectName'])) {
            $this->addError('subjectName', 'Name the asset or item being tracked.');
        }

        if ($kind === ObligationKind::Asset && blank($validated['subjectQuantity'])) {
            $this->addError('subjectQuantity', 'Enter the original quantity.');
        }

        if ($kind === ObligationKind::Asset && blank($validated['subjectUnit'])) {
            $this->addError('subjectUnit', 'Enter the unit for the asset quantity.');
        }

        if ($kind === ObligationKind::Service && blank($validated['subjectName'])) {
            $this->addError('subjectName', 'Name the service, work, or time being tracked.');
        }

        if ($kind === ObligationKind::Service && filled($validated['subjectQuantity']) && blank($validated['subjectUnit'])) {
            $this->addError('subjectUnit', 'Enter the unit when tracking a measurable service.');
        }

        if ($kind->isQuantityBased()) {
            foreach (['subjectQuantity', 'currentSubjectQuantity'] as $field) {
                if (! filled($this->{$field})) {
                    continue;
                }

                try {
                    Quantity::normalise($this->{$field}, QuantityMode::from($validated['quantityMode']));
                } catch (\InvalidArgumentException $exception) {
                    $this->addError($field, $exception->getMessage());
                }
            }
        }

        if ($kind === ObligationKind::Action && blank($validated['completionCriteria'])) {
            $this->addError('completionCriteria', 'Describe what completion looks like.');
        }

        if (filled($validated['estimatedValue']) && blank($validated['estimatedValueCurrency'])) {
            $this->addError('estimatedValueCurrency', 'Choose the currency for the estimated value.');
        }

        if ($validated['isConditional'] && blank($validated['conditionDescription'])) {
            $this->addError('conditionDescription', 'Describe what must happen before this obligation is due.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $updateObligation->handle($this->obligation, [
            'title' => $validated['title'],
            'tracking_mode' => $validated['trackingMode'],
            'category' => $validated['category'],
            'original_amount' => $validated['originalAmount'],
            'current_principal_balance' => $validated['currentPrincipalBalance'],
            'current_total_balance' => $validated['currentTotalBalance'],
            'minimum_payment_amount' => $validated['minimumPaymentAmount'],
            'started_on' => $validated['startedOn'],
            'due_on' => $validated['dueOn'],
            'next_due_on' => $validated['nextDueOn'],
            'data_confidence' => $validated['dataConfidence'],
            'is_interest_bearing' => $validated['isInterestBearing'],
            'description' => $validated['description'],
            'subject_name' => $kind->isQuantityBased() ? ($validated['subjectName'] ?: null) : null,
            'subject_quantity' => $kind->isQuantityBased() ? $validated['subjectQuantity'] : null,
            'current_subject_quantity' => $kind->isQuantityBased() ? $this->currentSubjectQuantity : null,
            'quantity_mode' => $kind->isQuantityBased() ? $validated['quantityMode'] : QuantityMode::Countable->value,
            'subject_unit' => $kind->isQuantityBased() ? ($validated['subjectUnit'] ?: null) : null,
            'subject_condition' => $kind === ObligationKind::Asset ? ($validated['subjectCondition'] ?: null) : null,
            'subject_details' => $kind->isQuantityBased() ? ($validated['subjectDetails'] ?: null) : null,
            'asset_type' => $kind === ObligationKind::Asset ? ($validated['assetType'] ?: 'physical') : null,
            'service_type' => $kind === ObligationKind::Service ? ($validated['serviceType'] ?: 'other') : null,
            'estimated_value' => $kind === ObligationKind::Asset ? $validated['estimatedValue'] : null,
            'estimated_value_currency' => $kind === ObligationKind::Asset ? ($validated['estimatedValueCurrency'] ?: null) : null,
            'completion_criteria' => in_array($kind, [ObligationKind::Action, ObligationKind::Service], true) ? ($validated['completionCriteria'] ?: null) : null,
            'is_conditional' => $validated['isConditional'],
            'condition_description' => $validated['isConditional'] ? ($validated['conditionDescription'] ?: null) : null,
            'condition_triggered_on' => $validated['conditionTriggeredOn'],
        ]);

        $this->redirectRoute('records.show', $this->obligation->record, navigate: true);
    }

    public function render(): View
    {
        Gate::authorize('update', $this->obligation);

        return view('livewire.obligations.edit')
            ->layout('layouts.app', ['title' => 'Edit '.$this->obligation->title]);
    }

    private function stringAttribute(Obligation $obligation, string $attribute): ?string
    {
        $value = $obligation->getAttribute($attribute);

        return $value === null ? null : (string) $value;
    }

    private function moneyAttribute(Obligation $obligation, string $attribute, string $currency): ?string
    {
        $value = $obligation->getAttribute($attribute);

        return $value === null ? null : MoneyAmount::majorInput((int) $value, $currency);
    }

    private function dateAttribute(Obligation $obligation, string $attribute): ?string
    {
        $value = $obligation->getAttribute($attribute);

        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) $value;
    }
}
