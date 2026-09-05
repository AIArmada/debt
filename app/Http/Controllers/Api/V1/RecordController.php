<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Obligations\CreateCollectionSchedule;
use App\Actions\Obligations\CreateObligation;
use App\Actions\Obligations\RecordObligationEvent;
use App\Actions\Obligations\RecordTransaction;
use App\Actions\Obligations\UpdateTransaction;
use App\Actions\Records\CreateRecord;
use App\Domain\Money\Currency;
use App\Domain\Money\MoneyAmount;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\CollectionScheduleResource;
use App\Http\Resources\DeliveryInstructionResource;
use App\Http\Resources\RecordResource;
use App\Models\FinancialProfile;
use App\Models\FinancialTransaction;
use App\Models\Obligation;
use App\Models\ObligationDeliveryInstruction;
use App\Models\ObligationParty;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\Record;
use App\Models\RecordParty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profileIds = $this->profileIds($request);
        $records = Record::query()->whereIn('profile_id', $profileIds)->where('is_archived', false)->with(['partyLinks.party', 'obligations.partyLinks.party', 'obligations.events', 'obligations.transactions', 'obligations.collectionSchedules', 'obligations.deliveryInstructions'])->latest()->get();

        return response()->json(['data' => RecordResource::collection($records)]);
    }

    public function show(Record $record): RecordResource
    {
        Gate::authorize('view', $record);

        return new RecordResource($record->load(['profile', 'partyLinks.party', 'documents', 'obligations.partyLinks.party', 'obligations.events.documents', 'obligations.events.createdBy', 'obligations.transactions.documents', 'obligations.collectionSchedules', 'obligations.deliveryInstructions']));
    }

    public function store(Request $request, CreateRecord $createRecord): RecordResource
    {
        $validated = $request->validate([
            'profile_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'party_id' => ['nullable', 'uuid'],
            'party' => ['nullable', 'array'],
            'party.preferred_name' => ['required_with:party', 'string', 'max:255'],
            'party.kind' => ['nullable', 'in:individual,organization,group,estate_or_trust,unidentified'],
            'sensitivity' => ['nullable', 'in:private,shared'],
            'obligation' => ['required', 'array'],
        ]);
        $profile = FinancialProfile::query()->whereKey($validated['profile_id'])->firstOrFail();
        $obligation = $this->validatedObligation($validated['obligation']);
        $record = $createRecord->handle($request->user(), $profile, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'party_id' => $validated['party_id'] ?? null,
            'party' => $validated['party'] ?? null,
            'sensitivity' => $validated['sensitivity'] ?? 'private',
        ], $obligation);

        return new RecordResource($record->load(['profile', 'partyLinks.party', 'obligations.partyLinks.party', 'obligations.events', 'obligations.transactions', 'obligations.collectionSchedules', 'obligations.deliveryInstructions']));
    }

    public function addObligation(Request $request, Record $record, CreateObligation $createObligation): JsonResponse
    {
        Gate::authorize('update', $record);
        $validated = $request->validate(['obligation' => ['required', 'array']]);
        $createObligation->handle($request->user(), $record, $this->validatedObligation($validated['obligation']));

        return (new RecordResource($record->refresh()->load(['profile', 'partyLinks.party', 'obligations.partyLinks.party', 'obligations.events', 'obligations.transactions', 'obligations.collectionSchedules', 'obligations.deliveryInstructions'])))->response()->setStatusCode(201);
    }

    public function addParty(Request $request, Record $record): JsonResponse
    {
        Gate::authorize('update', $record);
        $validated = $request->validate([
            'party_id' => ['required', 'uuid'],
            'role' => ['required', Rule::in(['other_party', 'beneficiary', 'obligor', 'guarantor', 'witness', 'representative', 'contact', 'custodian', 'service_recipient'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $party = Party::query()->whereKey($validated['party_id'])->where('profile_id', $record->profile_id)->whereNull('archived_at')->firstOrFail();
        if ($validated['role'] === 'other_party') {
            $record->partyLinks()->where('role', 'other_party')->update(['is_primary' => false]);
        }
        $link = RecordParty::query()->updateOrCreate(
            ['record_id' => $record->getKey(), 'party_id' => $party->getKey(), 'role' => $validated['role']],
            ['created_by_user_id' => $request->user()->getKey(), 'is_primary' => $validated['role'] === 'other_party', 'responsibility_scope' => 'record', 'status' => 'active', 'notes' => $validated['notes'] ?? null, 'visibility' => $record->sensitivity === 'shared' ? 'shared' : 'restricted'],
        );

        return response()->json(['data' => ['id' => $link->getKey(), 'party_id' => $party->getKey(), 'name' => $party->preferred_name, 'role' => $link->role, 'is_primary' => $link->is_primary]], 201);
    }

    public function removeParty(Record $record, RecordParty $recordParty): JsonResponse
    {
        Gate::authorize('update', $record);
        abort_unless($recordParty->record_id === $record->getKey(), 404);
        $recordParty->delete();

        return response()->json([], 204);
    }

    public function addObligationParty(Request $request, Record $record, Obligation $obligation): JsonResponse
    {
        $this->ensureChild($record, $obligation);
        Gate::authorize('update', $obligation);
        $validated = $request->validate([
            'party_id' => ['required', 'uuid'],
            'role' => ['required', Rule::in(['obligor', 'beneficiary', 'guarantor', 'payer', 'payee', 'creditor', 'debtor', 'service_recipient', 'custodian'])],
            'share_basis' => ['required', Rule::in(['full', 'percentage', 'fixed_amount', 'unspecified'])],
            'share_percent' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);
        $party = Party::query()->whereKey($validated['party_id'])->where('profile_id', $record->profile_id)->whereNull('archived_at')->firstOrFail();
        $link = ObligationParty::query()->updateOrCreate(
            ['obligation_id' => $obligation->getKey(), 'party_id' => $party->getKey(), 'role' => $validated['role']],
            ['created_by_user_id' => $request->user()->getKey(), 'share_basis' => $validated['share_basis'], 'share_percent' => $validated['share_basis'] === 'percentage' ? ($validated['share_percent'] ?? null) : null, 'status' => 'active'],
        );

        return response()->json(['data' => ['id' => $link->getKey(), 'party_id' => $party->getKey(), 'name' => $party->preferred_name, 'role' => $link->role, 'share_basis' => $link->share_basis, 'share_percent' => $link->share_percent]], 201);
    }

    public function transaction(Request $request, Record $record, Obligation $obligation, RecordTransaction $recordTransaction): JsonResponse
    {
        $this->ensureChild($record, $obligation);
        Gate::authorize('recordTransaction', $obligation);
        if (! $obligation->kind()->isMoney()) {
            throw ValidationException::withMessages(['obligation' => 'Only money obligations have financial transactions.']);
        }
        $validated = $request->validate($this->transactionRules(false));
        $transaction = $recordTransaction->handle($obligation, $this->transactionData($validated, $obligation));
        $updated = $obligation->fresh();

        return response()->json(['data' => $this->transactionPayload($transaction, $updated)], 201);
    }

    public function collectionSchedule(Request $request, Record $record, Obligation $obligation, CreateCollectionSchedule $createCollectionSchedule): CollectionScheduleResource
    {
        $this->ensureChild($record, $obligation);
        Gate::authorize('manageSchedule', $obligation);
        $validated = $request->validate([
            'mode' => ['required', 'in:manual_follow_up,bank_reconciliation'],
            'amount' => ['required_without:amount_minor', 'nullable', 'numeric', 'gt:0'],
            'amount_minor' => ['required_without:amount', 'nullable', 'integer', 'gt:0'],
            'currency' => ['required', Rule::in(Currency::codes())],
            'frequency' => ['required', 'in:daily,weekly,monthly,quarterly'],
            'starts_on' => ['required', 'date'],
            'next_due_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'collection_method' => ['required', 'in:bank_transfer,cash,payment_provider,other'],
            'collection_account_id' => ['nullable', 'uuid'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        $amount = $validated['amount'] ?? MoneyAmount::majorInput((int) $validated['amount_minor'], $validated['currency']);
        $schedule = $createCollectionSchedule->handle($request->user(), $obligation, [
            'mode' => $validated['mode'], 'amount' => (string) $amount, 'currency' => $validated['currency'], 'frequency' => $validated['frequency'],
            'starts_on' => $validated['starts_on'], 'next_due_on' => $validated['next_due_on'], 'ends_on' => $validated['ends_on'] ?? null,
            'collection_method' => $validated['collection_method'], 'collection_account_id' => $validated['collection_account_id'] ?? null,
            'grace_days' => $validated['grace_days'], 'note' => $validated['note'] ?? null,
        ]);

        return new CollectionScheduleResource($schedule);
    }

    public function deliveryInstruction(Request $request, Record $record, Obligation $obligation): DeliveryInstructionResource
    {
        $this->ensureChild($record, $obligation);
        Gate::authorize('manageDelivery', $obligation);
        $validated = $request->validate([
            'address_id' => ['nullable', 'uuid'],
            'recipient_party_id' => ['nullable', 'uuid'],
            'method' => ['required', 'in:delivery,in_person,courier,pickup_point,other'],
            'label' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:4000'],
        ]);
        $profile = $record->profile;
        $address = $validated['address_id'] === null ? null : PartyAddress::query()->whereKey($validated['address_id'])->whereHas('party', fn ($query) => $query->where('profile_id', $profile->getKey()))->firstOrFail();
        $recipient = $validated['recipient_party_id'] === null ? null : Party::query()->whereKey($validated['recipient_party_id'])->where('profile_id', $profile->getKey())->whereNull('archived_at')->firstOrFail();
        if ($address === null && blank($validated['instructions'] ?? null)) {
            throw ValidationException::withMessages(['address_id' => 'Choose an address or add handover instructions.']);
        }
        $instruction = ObligationDeliveryInstruction::create([
            'obligation_id' => $obligation->getKey(), 'address_id' => $address?->getKey(), 'recipient_party_id' => $recipient?->getKey(), 'created_by_user_id' => $request->user()->getKey(),
            'method' => $validated['method'], 'label' => trim($validated['label']), 'instructions' => $validated['instructions'] ?? null, 'status' => 'active', 'verification_status' => 'needs_review',
            'shown_snapshot' => ['label' => trim($validated['label']), 'method' => $validated['method'], 'recipient' => $recipient?->preferred_name, 'address' => $address?->only(['label', 'address_line_1', 'address_line_2', 'city', 'region', 'postal_code', 'country_code']), 'instructions' => $validated['instructions'] ?? null],
        ]);

        return new DeliveryInstructionResource($instruction);
    }

    public function updateTransaction(Request $request, Record $record, Obligation $obligation, FinancialTransaction $transaction, UpdateTransaction $updateTransaction): JsonResponse
    {
        $this->ensureChild($record, $obligation);
        abort_unless($transaction->obligation_id === $obligation->getKey(), 404);
        Gate::authorize('recordTransaction', $obligation);
        $validated = $request->validate($this->transactionRules(true));
        $updated = $updateTransaction->handle($obligation, $transaction, $this->transactionData($validated, $obligation));

        return response()->json(['data' => $this->transactionPayload($updated, $obligation->fresh())]);
    }

    public function event(Request $request, Record $record, Obligation $obligation, RecordObligationEvent $recordObligationEvent): JsonResponse
    {
        $this->ensureChild($record, $obligation);
        Gate::authorize('recordEvent', $obligation);
        $validated = $request->validate(['event_type' => ['required', 'string'], 'quantity' => ['nullable', 'numeric', 'gt:0'], 'occurred_on' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:4000']]);
        $event = $recordObligationEvent->handle($request->user(), $obligation, ['event_type' => $validated['event_type'], 'quantity' => isset($validated['quantity']) ? (string) $validated['quantity'] : null, 'occurred_on' => $validated['occurred_on'] ?? null, 'note' => $validated['note'] ?? null]);

        return response()->json(['data' => ['id' => $event->getKey(), 'event_type' => $event->event_type, 'quantity' => $event->quantity, 'quantity_effect' => $event->quantity_effect, 'unit' => $event->unit, 'occurred_on' => $event->occurred_on?->toDateString(), 'note' => $event->note, 'obligation_status' => $obligation->fresh()->status, 'outstanding_quantity' => $obligation->fresh()->current_subject_quantity]], 201);
    }

    /** @return Collection<int, string> */
    private function profileIds(Request $request)
    {
        return FinancialProfile::query()->where('owner_user_id', $request->user()->getKey())->orWhereHas('members', fn ($query) => $query->where('user_id', $request->user()->getKey())->whereNotNull('accepted_at')->whereNull('revoked_at'))->pluck('id');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function validatedObligation(array $data): array
    {
        $validated = validator($data, [
            'direction' => ['required', 'in:payable,receivable'], 'tracking_mode' => ['nullable', 'in:snapshot,ledger'], 'obligation_kind' => ['required', 'in:money,asset,service,action'], 'title' => ['required', 'string', 'max:160'], 'category' => ['required', 'string', 'max:60'], 'currency' => ['nullable', Rule::in(Currency::codes())], 'original_amount' => ['nullable', 'numeric', 'min:0'], 'current_total_balance' => ['nullable', 'numeric', 'min:0'], 'minimum_payment_amount' => ['nullable', 'numeric', 'min:0'], 'next_due_on' => ['nullable', 'date'], 'is_interest_bearing' => ['boolean'], 'description' => ['nullable', 'string', 'max:4000'], 'subject_name' => ['nullable', 'string', 'max:255'], 'subject_quantity' => ['nullable', 'numeric', 'gt:0'], 'quantity_mode' => ['required_if:obligation_kind,asset,service', 'in:countable,measurable'], 'subject_unit' => ['nullable', 'string', 'max:60'], 'subject_condition' => ['nullable', 'string', 'max:60'], 'subject_details' => ['nullable', 'string', 'max:4000'], 'asset_type' => ['nullable', 'string', 'max:40'], 'service_type' => ['nullable', 'string', 'max:40'], 'estimated_value' => ['nullable', 'numeric', 'min:0'], 'estimated_value_currency' => ['nullable', Rule::in(Currency::codes())], 'completion_criteria' => ['nullable', 'string', 'max:4000'], 'is_conditional' => ['boolean'], 'condition_description' => ['nullable', 'string', 'max:4000'], 'condition_triggered_on' => ['nullable', 'date'],
        ])->validate();
        $kind = ObligationKind::from($validated['obligation_kind']);
        if (! array_key_exists($validated['category'], $kind->categoryOptions())) {
            throw ValidationException::withMessages(['category' => 'Choose a category that matches the obligation type.']);
        }
        $quantityMode = $kind->isQuantityBased()
            ? QuantityMode::tryFrom((string) $validated['quantity_mode'])
            : QuantityMode::Countable;
        if ($kind->isQuantityBased() && filled($validated['subject_quantity'] ?? null)
            && ! Quantity::isModeCompatible($quantityMode, $validated['subject_unit'] ?? null)) {
            throw ValidationException::withMessages([
                'subject_unit' => 'Measurable quantities need a unit such as gram, kilogram, hour, or metre. Use whole units for complete items like cameras.',
            ]);
        }
        if ($kind->isMoney() && blank($validated['currency'] ?? null)) {
            throw ValidationException::withMessages(['currency' => 'Money obligations require a currency.']);
        }
        if ($kind->isMoney() && ($validated['tracking_mode'] ?? 'snapshot') === 'snapshot' && blank($validated['current_total_balance'] ?? null)) {
            throw ValidationException::withMessages(['current_total_balance' => 'Snapshot tracking requires a current balance.']);
        }
        if ($kind === ObligationKind::Asset && blank($validated['subject_name'] ?? null)) {
            throw ValidationException::withMessages(['subject_name' => 'Asset obligations require an asset name.']);
        }
        if ($kind === ObligationKind::Asset && blank($validated['subject_quantity'] ?? null)) {
            throw ValidationException::withMessages(['subject_quantity' => 'Asset obligations require an outstanding quantity.']);
        }
        if ($kind === ObligationKind::Asset && blank($validated['subject_unit'] ?? null)) {
            throw ValidationException::withMessages(['subject_unit' => 'Asset obligations require a unit.']);
        }
        if ($kind->isQuantityBased() && filled($validated['subject_quantity'] ?? null)) {
            try {
                Quantity::normalise($validated['subject_quantity'], $quantityMode);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['subject_quantity' => $exception->getMessage()]);
            }
        }
        if ($kind === ObligationKind::Service && blank($validated['subject_name'] ?? null)) {
            throw ValidationException::withMessages(['subject_name' => 'Service obligations require a service name.']);
        }
        if ($kind === ObligationKind::Action && blank($validated['completion_criteria'] ?? null)) {
            throw ValidationException::withMessages(['completion_criteria' => 'Action obligations require completion criteria.']);
        }
        if (filled($validated['estimated_value'] ?? null) && blank($validated['estimated_value_currency'] ?? null)) {
            throw ValidationException::withMessages(['estimated_value_currency' => 'An estimated value requires a currency.']);
        }
        if (($validated['is_conditional'] ?? false) && blank($validated['condition_description'] ?? null)) {
            throw ValidationException::withMessages(['condition_description' => 'Conditional obligations require a condition description.']);
        }

        return ['direction' => $validated['direction'], 'obligation_kind' => $kind->value, 'tracking_mode' => $validated['tracking_mode'] ?? 'snapshot', 'title' => $validated['title'], 'category' => $validated['category'], 'currency' => $kind->isMoney() ? ($validated['currency'] ?? null) : null, 'original_amount' => $kind->isMoney() ? ($validated['original_amount'] ?? null) : null, 'current_total_balance' => $kind->isMoney() ? ($validated['current_total_balance'] ?? null) : null, 'minimum_payment_amount' => $kind->isMoney() ? ($validated['minimum_payment_amount'] ?? null) : null, 'next_due_on' => $validated['next_due_on'] ?? null, 'is_interest_bearing' => $kind->isMoney() && ($validated['is_interest_bearing'] ?? false), 'description' => $validated['description'] ?? '', 'subject_name' => $kind->isQuantityBased() ? ($validated['subject_name'] ?? null) : null, 'subject_quantity' => $kind->isQuantityBased() ? (isset($validated['subject_quantity']) ? (string) $validated['subject_quantity'] : null) : null, 'quantity_mode' => $kind->isQuantityBased() ? $quantityMode->value : QuantityMode::Countable->value, 'subject_unit' => $kind->isQuantityBased() ? ($validated['subject_unit'] ?? null) : null, 'subject_condition' => $kind === ObligationKind::Asset ? ($validated['subject_condition'] ?? null) : null, 'subject_details' => $kind->isQuantityBased() ? ($validated['subject_details'] ?? null) : null, 'asset_type' => $kind === ObligationKind::Asset ? ($validated['asset_type'] ?? 'physical') : null, 'service_type' => $kind === ObligationKind::Service ? ($validated['service_type'] ?? 'other') : null, 'estimated_value' => $kind === ObligationKind::Asset ? (isset($validated['estimated_value']) ? (string) $validated['estimated_value'] : null) : null, 'estimated_value_currency' => $kind === ObligationKind::Asset ? ($validated['estimated_value_currency'] ?? null) : null, 'completion_criteria' => in_array($kind, [ObligationKind::Action, ObligationKind::Service], true) ? ($validated['completion_criteria'] ?? null) : null, 'is_conditional' => $validated['is_conditional'] ?? false, 'condition_description' => ($validated['is_conditional'] ?? false) ? ($validated['condition_description'] ?? null) : null, 'condition_triggered_on' => ($validated['is_conditional'] ?? false) ? ($validated['condition_triggered_on'] ?? null) : null];
    }

    /** @return array<string, string|array<int, mixed>> */
    private function transactionRules(bool $entryRequired): array
    {
        return ['amount' => ['required_without:amount_minor', 'nullable', 'numeric', 'gt:0'], 'amount_minor' => ['required_without:amount', 'nullable', 'integer', 'gt:0'], 'currency' => ['nullable', Rule::in(Currency::codes())], 'status' => ['required', 'in:planned,submitted,confirmed,failed,cancelled'], 'entry_type' => [$entryRequired ? 'required' : 'nullable', 'in:payment,collection,advance,interest,fee,adjustment,write_off,opening_balance'], 'balance_effect' => ['nullable', 'in:increase,decrease'], 'occurred_on' => ['nullable', 'date'], 'external_reference' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string', 'max:4000'], 'repayment_plan_allocation_id' => ['nullable', 'uuid'], 'collection_schedule_id' => ['nullable', 'uuid']];
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    private function transactionData(array $validated, Obligation $obligation): array
    {
        return ['status' => $validated['status'], 'amount' => (string) ($validated['amount'] ?? '0'), 'amount_minor' => $validated['amount_minor'] ?? null, 'currency' => $validated['currency'] ?? $obligation->currency, 'occurred_on' => $validated['occurred_on'] ?? null, 'external_reference' => $validated['external_reference'] ?? null, 'note' => $validated['note'] ?? null, 'entry_type' => $validated['entry_type'] ?? null, 'balance_effect' => $validated['balance_effect'] ?? null, 'repayment_plan_allocation_id' => $validated['repayment_plan_allocation_id'] ?? null, 'collection_schedule_id' => $validated['collection_schedule_id'] ?? null];
    }

    /** @return array<string, mixed> */
    private function transactionPayload(FinancialTransaction $transaction, Obligation $obligation): array
    {
        return [
            'id' => $transaction->getKey(),
            'repayment_plan_allocation_id' => $transaction->repayment_plan_allocation_id,
            'collection_schedule_id' => $transaction->collection_schedule_id,
            'entry_type' => $transaction->entry_type,
            'balance_effect' => $transaction->balance_effect,
            'status' => $transaction->status,
            'amount' => MoneyAmount::majorInput((int) $transaction->amount, (string) $transaction->currency),
            'amount_minor' => $transaction->amount,
            'currency' => $transaction->currency,
            'balance_before' => MoneyAmount::majorInputNullable($transaction->balance_before, (string) $transaction->currency),
            'balance_before_minor' => $transaction->balance_before,
            'balance_after' => MoneyAmount::majorInputNullable($transaction->balance_after, (string) $transaction->currency),
            'balance_after_minor' => $transaction->balance_after,
            'obligation_balance' => MoneyAmount::majorInputNullable($obligation->current_total_balance, (string) $obligation->currency),
            'obligation_balance_minor' => $obligation->current_total_balance,
            'currency_balances' => collect($obligation->currencyBalances())->mapWithKeys(fn (int $amount, string $currency): array => [$currency => MoneyAmount::majorInput($amount, $currency)])->all(),
            'currency_balances_minor' => $obligation->currencyBalances(),
            'currency_positions' => collect($obligation->currencyPositions())->mapWithKeys(fn (array $position): array => [$position['currency'] => [...$position, 'amount' => MoneyAmount::majorInput((int) $position['amount'], $position['currency'])]])->all(),
            'currency_positions_minor' => $obligation->currencyPositions(),
            'current_position' => ['direction' => $obligation->currentPositionDirection(), 'amount' => MoneyAmount::majorInput($obligation->currentPositionAmount(), (string) $obligation->currency), 'amount_minor' => $obligation->currentPositionAmount(), 'label' => $obligation->currentPositionLabel(), 'is_reversed' => $obligation->isPositionReversed()],
        ];
    }

    private function ensureChild(Record $record, Obligation $obligation): void
    {
        abort_unless($obligation->record_id === $record->getKey(), 404);
        Gate::authorize('view', $record);
    }
}
