<?php

namespace App\Livewire\Records;

use App\Actions\Records\CreateRecord;
use App\Domain\Money\Currency;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\FinancialProfile;
use App\Models\Record;
use App\Services\ProfileAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Create extends Component
{
    public ?string $profileId = null;

    public string $recordTitle = '';

    public string $recordDescription = '';

    public string $partyMode = 'none';

    public ?string $primaryPartyId = null;

    public string $newPartyName = '';

    public string $newPartyKind = 'individual';

    public string $sensitivity = 'private';

    public string $direction = 'payable';

    public string $obligationKind = 'money';

    public string $trackingMode = 'snapshot';

    public string $obligationTitle = '';

    public string $category = 'personal_loan';

    public ?string $currency = 'MYR';

    public ?string $originalAmount = null;

    public ?string $currentTotalBalance = null;

    public ?string $minimumPaymentAmount = null;

    public ?string $nextDueOn = null;

    public bool $isInterestBearing = false;

    public string $subjectName = '';

    public ?string $subjectQuantity = null;

    public string $quantityMode = 'countable';

    public string $subjectUnit = 'item';

    public string $subjectCondition = '';

    public string $subjectDetails = '';

    public string $assetType = 'physical';

    public string $serviceType = 'time';

    public ?string $estimatedValue = null;

    public ?string $estimatedValueCurrency = 'MYR';

    public string $completionCriteria = '';

    public bool $isConditional = false;

    public bool $showReview = false;

    public string $conditionDescription = '';

    public ?string $conditionTriggeredOn = null;

    public string $description = '';

    public function mount(?Record $record = null): void
    {
        $profile = $this->selectedProfile();
        $this->profileId = $profile->getKey();
        $this->currency = $profile->base_currency;
        $this->estimatedValueCurrency = $profile->base_currency;
    }

    public function updatedProfileId(): void
    {
        $profile = $this->profiles()->findOrFail($this->profileId);
        $this->currency = $profile->base_currency;
        $this->estimatedValueCurrency = $profile->base_currency;
        $this->primaryPartyId = null;
        $this->partyMode = 'none';
        session()->put('selected_profile_id', $profile->getKey());
    }

    public function updatedObligationKind(): void
    {
        // Each obligation kind has a different data contract. Clear values from
        // the previous contract so hidden inputs can never leak into validation
        // or into the next obligation that is saved.
        $profile = $this->profiles()->find($this->profileId);
        $baseCurrency = $profile?->base_currency ?? 'MYR';

        $this->trackingMode = 'snapshot';
        $this->category = ObligationKind::from($this->obligationKind)->defaultCategory();
        $this->currency = $baseCurrency;
        $this->originalAmount = null;
        $this->currentTotalBalance = null;
        $this->minimumPaymentAmount = null;
        $this->isInterestBearing = false;
        $this->subjectName = '';
        $this->subjectQuantity = null;
        $this->quantityMode = QuantityMode::defaultFor(ObligationKind::from($this->obligationKind))->value;
        $this->subjectUnit = $this->obligationKind === 'asset' ? 'item' : '';
        $this->subjectCondition = '';
        $this->subjectDetails = '';
        $this->assetType = 'physical';
        $this->serviceType = 'time';
        $this->estimatedValue = null;
        $this->estimatedValueCurrency = $baseCurrency;
        $this->completionCriteria = '';
        $this->resetValidation();
    }

    public function preview(): void
    {
        $validated = $this->validate(array_merge($this->recordRules(), $this->obligationRules()));
        $this->validatePartySelection($validated);
        $this->validatedObligation();
        $this->showReview = true;
    }

    public function submit(CreateRecord $createRecord): void
    {
        if (! $this->showReview) {
            $this->preview();

            return;
        }

        $this->save($createRecord);
    }

    public function save(CreateRecord $createRecord): void
    {
        $validated = $this->validate(array_merge($this->recordRules(), $this->obligationRules()));
        $this->validatePartySelection($validated);
        if ($validated['partyMode'] === 'new' && blank($validated['newPartyName'] ?? null)) {
            throw ValidationException::withMessages(['newPartyName' => 'Give this party a name, or choose an existing party.']);
        }
        if ($validated['partyMode'] === 'existing' && blank($validated['primaryPartyId'] ?? null)) {
            throw ValidationException::withMessages(['primaryPartyId' => 'Choose the party involved in this record.']);
        }
        $validatedObligation = $this->validatedObligation();
        $validated = array_merge($validated, $validatedObligation);

        $profile = $this->profiles()->findOrFail($validated['profileId']);
        session()->put('selected_profile_id', $profile->getKey());

        $record = $createRecord->handle(
            Auth::user(),
            $profile,
            [
                'title' => $validated['recordTitle'],
                'description' => $validated['recordDescription'],
                'party_id' => $validated['partyMode'] === 'existing' ? $validated['primaryPartyId'] : null,
                'party' => $validated['partyMode'] === 'new' ? [
                    'preferred_name' => $validated['newPartyName'],
                    'kind' => $validated['newPartyKind'],
                ] : null,
                'sensitivity' => $validated['sensitivity'],
                'is_archived' => false,
            ],
            $this->obligationData($validated),
        );

        $this->redirectRoute('records.show', $record, navigate: true);
    }

    /** @return array<string, array<int, string>> */
    protected function recordRules(): array
    {
        return [
            'profileId' => ['required', 'uuid'],
            'recordTitle' => ['required', 'string', 'max:160'],
            'recordDescription' => ['nullable', 'string', 'max:4000'],
            'partyMode' => ['required', 'in:none,existing,new'],
            'primaryPartyId' => ['nullable', 'uuid'],
            'newPartyName' => ['nullable', 'string', 'max:255'],
            'newPartyKind' => ['required_if:partyMode,new', 'in:individual,organization,group,estate_or_trust,unidentified'],
            'sensitivity' => ['required', 'in:private,shared'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    protected function obligationRules(): array
    {
        $rules = [
            'direction' => ['required', 'in:payable,receivable'],
            'obligationKind' => ['required', 'in:money,asset,service,action'],
            'trackingMode' => ['required', 'in:snapshot,ledger'],
            'obligationTitle' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(array_keys(ObligationKind::from($this->obligationKind)->categoryOptions()))],
            'nextDueOn' => ['nullable', 'date'],
            'isConditional' => ['boolean'],
            'conditionDescription' => ['nullable', 'string', 'max:4000'],
            'conditionTriggeredOn' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:4000'],
        ];

        return array_merge($rules, match ($this->obligationKind) {
            'money' => [
                'trackingMode' => ['required', 'in:snapshot,ledger'],
                'currency' => ['required', Rule::in(Currency::codes())],
                'originalAmount' => ['nullable', 'numeric', 'min:0'],
                'currentTotalBalance' => [$this->trackingMode === 'snapshot' ? 'required' : 'nullable', 'numeric', 'min:0'],
                'minimumPaymentAmount' => ['nullable', 'numeric', 'min:0'],
                'isInterestBearing' => ['boolean'],
            ],
            'asset' => [
                'subjectName' => ['required', 'string', 'max:255'],
                'subjectQuantity' => ['required', 'numeric', 'gt:0'],
                'quantityMode' => ['required', 'in:countable,measurable'],
                'subjectUnit' => ['required', 'string', 'max:60'],
                'subjectCondition' => ['nullable', 'string', 'max:60'],
                'subjectDetails' => ['nullable', 'string', 'max:4000'],
                'assetType' => ['required', 'in:physical,document,digital,access,other'],
                'estimatedValue' => ['nullable', 'numeric', 'min:0'],
                'estimatedValueCurrency' => ['nullable', Rule::in(Currency::codes())],
            ],
            'service' => [
                'subjectName' => ['required', 'string', 'max:255'],
                'subjectQuantity' => ['nullable', 'numeric', 'gt:0'],
                'quantityMode' => ['required', 'in:countable,measurable'],
                'subjectUnit' => ['nullable', 'string', 'max:60'],
                'subjectDetails' => ['nullable', 'string', 'max:4000'],
                'serviceType' => ['required', 'in:time,skilled_work,care,transport,professional,other'],
                'completionCriteria' => ['nullable', 'string', 'max:4000'],
            ],
            'action' => [
                'completionCriteria' => ['required', 'string', 'max:4000'],
            ],
            default => [],
        });
    }

    /** @return array<string, mixed> */
    protected function validatedObligation(): array
    {
        $validated = $this->validate($this->obligationRules());
        $validated = array_merge($this->obligationState(), $validated);
        $this->validateObligationRequirements($validated);

        return $validated;
    }

    /** @return array<string, mixed> */
    protected function obligationState(): array
    {
        return [
            'direction' => $this->direction,
            'obligationKind' => $this->obligationKind,
            'trackingMode' => $this->trackingMode,
            'obligationTitle' => $this->obligationTitle,
            'category' => $this->category,
            'currency' => $this->currency,
            'originalAmount' => $this->originalAmount,
            'currentTotalBalance' => $this->currentTotalBalance,
            'minimumPaymentAmount' => $this->minimumPaymentAmount,
            'nextDueOn' => $this->nextDueOn,
            'isInterestBearing' => $this->isInterestBearing,
            'subjectName' => $this->subjectName,
            'subjectQuantity' => $this->subjectQuantity,
            'quantityMode' => $this->quantityMode,
            'subjectUnit' => $this->subjectUnit,
            'subjectCondition' => $this->subjectCondition,
            'subjectDetails' => $this->subjectDetails,
            'assetType' => $this->assetType,
            'serviceType' => $this->serviceType,
            'estimatedValue' => $this->estimatedValue,
            'estimatedValueCurrency' => $this->estimatedValueCurrency,
            'completionCriteria' => $this->completionCriteria,
            'isConditional' => $this->isConditional,
            'conditionDescription' => $this->conditionDescription,
            'conditionTriggeredOn' => $this->conditionTriggeredOn,
            'description' => $this->description,
        ];
    }

    /** @return list<array{label: string, value: string}> */
    public function reviewSummary(): array
    {
        $kind = ObligationKind::from($this->obligationKind);
        $summary = [
            ['label' => 'Direction', 'value' => $this->direction === 'payable' ? 'I need to pay / return / do it' : 'Someone owes / must return / do it for me'],
            ['label' => 'What is owed', 'value' => $kind->label()],
            ['label' => $kind->categoryFieldLabel(), 'value' => $kind->categoryOptions()[$this->category] ?? 'Not selected'],
            ['label' => 'Obligation', 'value' => $this->obligationTitle],
        ];

        if ($kind->isMoney()) {
            $summary[] = ['label' => $this->trackingMode === 'ledger' ? 'Opening balance' : 'Current balance now', 'value' => filled($this->currentTotalBalance) ? $this->currentTotalBalance.' '.$this->currency : 'Not recorded'];
            $summary[] = ['label' => 'Original amount', 'value' => filled($this->originalAmount) ? $this->originalAmount.' '.$this->currency : 'Not recorded'];
            $summary[] = ['label' => 'Minimum payment', 'value' => filled($this->minimumPaymentAmount) ? $this->minimumPaymentAmount.' '.$this->currency : 'Not set'];
        } elseif ($kind->isQuantityBased()) {
            $summary[] = ['label' => 'Outstanding', 'value' => $this->subjectQuantity.' '.$this->subjectUnit];
            $summary[] = ['label' => 'Quantity style', 'value' => QuantityMode::from($this->quantityMode)->label()];
            $summary[] = ['label' => 'Item / service', 'value' => $this->subjectName];
            if ($kind === ObligationKind::Asset && filled($this->estimatedValue)) {
                $summary[] = ['label' => 'Estimated value', 'value' => $this->estimatedValue.' '.$this->estimatedValueCurrency.' · context only'];
            }
        } else {
            $summary[] = ['label' => 'Completion', 'value' => $this->completionCriteria];
        }

        if (filled($this->nextDueOn)) {
            $summary[] = ['label' => 'Due', 'value' => $this->nextDueOn];
        }

        return $summary;
    }

    /** @param array<string, mixed> $validated */
    protected function validateObligationRequirements(array $validated): void
    {
        $kind = ObligationKind::from($validated['obligationKind']);

        if ($kind->isMoney() && $validated['trackingMode'] === 'snapshot' && blank($validated['currentTotalBalance'])) {
            throw ValidationException::withMessages(['currentTotalBalance' => 'Enter the current balance for snapshot tracking.']);
        }
        if ($kind->isMoney() && blank($validated['currency'])) {
            throw ValidationException::withMessages(['currency' => 'Choose a currency for a money obligation.']);
        }
        if ($kind === ObligationKind::Asset && blank($validated['subjectName'])) {
            throw ValidationException::withMessages(['subjectName' => 'Name the asset or item being tracked.']);
        }
        if ($kind === ObligationKind::Asset && blank($validated['subjectQuantity'])) {
            throw ValidationException::withMessages(['subjectQuantity' => 'Enter the outstanding quantity.']);
        }
        if ($kind === ObligationKind::Asset && blank($validated['subjectUnit'])) {
            throw ValidationException::withMessages(['subjectUnit' => 'Enter the unit for the asset quantity.']);
        }
        if ($kind === ObligationKind::Service && blank($validated['subjectName'])) {
            throw ValidationException::withMessages(['subjectName' => 'Name the service, work, or time being tracked.']);
        }
        if ($kind === ObligationKind::Service && filled($validated['subjectQuantity']) && blank($validated['subjectUnit'])) {
            throw ValidationException::withMessages(['subjectUnit' => 'Enter the unit when tracking a measurable service.']);
        }
        if ($kind->isQuantityBased() && filled($validated['subjectQuantity'])) {
            $quantityMode = QuantityMode::from($validated['quantityMode']);

            if (! Quantity::isModeCompatible($quantityMode, $validated['subjectUnit'])) {
                throw ValidationException::withMessages([
                    'quantityMode' => 'Measurable quantities need a unit such as gram, kilogram, hour, or metre. Use whole units for complete items like cameras.',
                ]);
            }

            try {
                Quantity::normalise($validated['subjectQuantity'], $quantityMode);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['subjectQuantity' => $exception->getMessage()]);
            }
        }
        if ($kind === ObligationKind::Action && blank($validated['completionCriteria'])) {
            throw ValidationException::withMessages(['completionCriteria' => 'Describe what completion looks like.']);
        }
        if (filled($validated['estimatedValue']) && blank($validated['estimatedValueCurrency'])) {
            throw ValidationException::withMessages(['estimatedValueCurrency' => 'Choose the currency for the estimated value.']);
        }
        if ($validated['isConditional'] && blank($validated['conditionDescription'])) {
            throw ValidationException::withMessages(['conditionDescription' => 'Describe what must happen before this obligation is due.']);
        }
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    protected function obligationData(array $validated): array
    {
        $kind = ObligationKind::from($validated['obligationKind']);

        return [
            'direction' => $validated['direction'],
            'obligation_kind' => $kind->value,
            'tracking_mode' => $validated['trackingMode'],
            'title' => $validated['obligationTitle'],
            'category' => $validated['category'],
            'currency' => $kind->isMoney() ? $validated['currency'] : null,
            'original_amount' => $kind->isMoney() ? $validated['originalAmount'] : null,
            'current_total_balance' => $kind->isMoney() ? $validated['currentTotalBalance'] : null,
            'minimum_payment_amount' => $kind->isMoney() ? $validated['minimumPaymentAmount'] : null,
            'next_due_on' => $validated['nextDueOn'],
            'is_interest_bearing' => $kind->isMoney() && $validated['isInterestBearing'],
            'description' => $validated['description'],
            'subject_name' => $kind->isQuantityBased() ? ($validated['subjectName'] ?: null) : null,
            'subject_quantity' => $kind->isQuantityBased() ? $validated['subjectQuantity'] : null,
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
        ];
    }

    public function render(): View
    {
        $profile = $this->profiles()->find($this->profileId);

        return view('livewire.records.create', ['profiles' => $this->profiles()->get(), 'parties' => $profile?->parties()->whereNull('archived_at')->orderBy('preferred_name')->get() ?? collect()])
            ->layout('layouts.app', ['title' => 'New record']);
    }

    /** @return Builder<FinancialProfile> */
    protected function profiles(): Builder
    {
        return app(ProfileAccess::class)->accessibleProfiles(Auth::user());
    }

    protected function selectedProfile(): FinancialProfile
    {
        $selectedProfileId = session('selected_profile_id');

        return is_string($selectedProfileId)
            ? $this->profiles()->whereKey($selectedProfileId)->first() ?? $this->profiles()->firstOrFail()
            : $this->profiles()->firstOrFail();
    }

    /** @param array<string, mixed> $validated */
    protected function validatePartySelection(array $validated): void
    {
        if ($validated['partyMode'] === 'new' && blank($validated['newPartyName'] ?? null)) {
            throw ValidationException::withMessages(['newPartyName' => 'Give this party a name, or choose an existing party.']);
        }

        if ($validated['partyMode'] === 'existing') {
            if (blank($validated['primaryPartyId'] ?? null)) {
                throw ValidationException::withMessages(['primaryPartyId' => 'Choose the party involved in this record.']);
            }

            $belongsToProfile = $this->profiles()
                ->whereKey($validated['profileId'])
                ->whereHas('parties', fn ($query) => $query->whereKey($validated['primaryPartyId'])->whereNull('archived_at'))
                ->exists();

            if (! $belongsToProfile) {
                throw ValidationException::withMessages(['primaryPartyId' => 'Choose a party from the selected profile.']);
            }
        }
    }
}
