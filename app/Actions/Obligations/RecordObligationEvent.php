<?php

namespace App\Actions\Obligations;

use App\Domain\Money\Decimal;
use App\Domain\Obligations\ObligationKind;
use App\Domain\Obligations\Quantity;
use App\Domain\Obligations\QuantityMode;
use App\Models\Obligation;
use App\Models\ObligationEvent;
use App\Models\User;
use App\Services\ActivityNotifier;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecordObligationEvent
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ActivityNotifier $activityNotifier,
    ) {}

    /** @param array{event_type: string, quantity: string|null, occurred_on: string|null, note: string|null} $data */
    public function handle(User $user, Obligation $obligation, array $data): ObligationEvent
    {
        Gate::forUser($user)->authorize('recordEvent', $obligation);

        return DB::transaction(function () use ($user, $obligation, $data): ObligationEvent {
            $lockedObligation = Obligation::query()
                ->whereKey($obligation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($user)->authorize('recordEvent', $lockedObligation);

            $kind = $lockedObligation->kind();
            $eventType = $data['event_type'];
            $this->ensureAllowedEvent($kind, $eventType);
            if ($kind->isQuantityBased()
                && ! Quantity::isModeCompatible($lockedObligation->quantityMode(), $lockedObligation->subject_unit)) {
                throw ValidationException::withMessages([
                    'quantity' => 'Choose a measurable unit such as gram, kilogram, hour, or metre before recording fractional progress.',
                ]);
            }
            $quantity = $kind->isQuantityBased()
                ? $this->normaliseQuantity($data['quantity'], $lockedObligation->quantityMode())
                : null;
            $quantityEffect = null;

            if ($kind->isQuantityBased()) {
                [$quantity, $quantityEffect] = $this->applyQuantityEvent($lockedObligation, $kind, $eventType, $quantity);
            } elseif ($eventType === 'fulfilled' || $eventType === 'waived') {
                $lockedObligation->status = $eventType === 'fulfilled' ? 'settled' : 'waived';
                $lockedObligation->settled_at = now();
                $lockedObligation->save();
            }

            $event = $lockedObligation->events()->create([
                'created_by_user_id' => $user->getKey(),
                'event_type' => $eventType,
                'quantity' => $quantity,
                'quantity_effect' => $quantityEffect,
                'unit' => $kind->isQuantityBased() ? $lockedObligation->subject_unit : null,
                'occurred_on' => $data['occurred_on'],
                'note' => $data['note'],
            ]);

            $this->auditLogger->record(
                $lockedObligation->record->profile,
                $user,
                ObligationEvent::class,
                $event->getKey(),
                'created',
                after: $event->only(['obligation_id', 'event_type', 'quantity', 'quantity_effect', 'unit', 'occurred_on', 'note']),
                metadata: [
                    'obligation_status' => $lockedObligation->status,
                    'outstanding_quantity' => $lockedObligation->current_subject_quantity,
                ],
            );

            $this->activityNotifier->notifyObligation(
                $lockedObligation,
                $kind->isMoney() ? 'collection_context_recorded' : 'fulfillment_event_recorded',
                $kind->isMoney() ? 'Collection context recorded' : 'A record received a fulfillment update',
                $kind->isMoney() ? 'A context note was added to one of your private collection records.' : 'A progress or fulfillment update was added to one of your private records.',
                in_array($eventType, ['missed', 'lost'], true) ? 'urgent' : 'normal',
                [
                    'event_type' => $eventType,
                    'quantity' => $quantity,
                    'status' => $lockedObligation->status,
                ],
            );

            return $event;
        });
    }

    private function ensureAllowedEvent(ObligationKind $kind, string $eventType): void
    {
        $allowed = match ($kind) {
            ObligationKind::Asset => ['added', 'returned', 'partially_returned', 'replaced', 'lost', 'condition_update', 'waived', 'note'],
            ObligationKind::Service => ['added', 'progress', 'fulfilled', 'missed', 'scope_change', 'waived', 'note'],
            ObligationKind::Action => ['progress', 'fulfilled', 'missed', 'scope_change', 'waived', 'note'],
            ObligationKind::Money => ['note', 'context', 'missed', 'postponed', 'promised'],
        };

        if (! in_array($eventType, $allowed, true)) {
            throw ValidationException::withMessages([
                'event_type' => 'Choose an update that matches this type of obligation.',
            ]);
        }
    }

    /** @return array{0: numeric-string|null, 1: string|null} */
    private function applyQuantityEvent(Obligation $obligation, ObligationKind $kind, string $eventType, ?string $quantity): array
    {
        $current = $this->decimalString($obligation->current_subject_quantity)
            ?? $this->decimalString($obligation->subject_quantity);

        if ($current !== null && ! Quantity::isValid($current, $obligation->quantityMode(), false)) {
            throw ValidationException::withMessages([
                'quantity' => 'Review the outstanding quantity first. Whole-unit items cannot have a fractional quantity.',
            ]);
        }

        $increaseEvents = ['added'];
        $decreaseEvents = $kind === ObligationKind::Asset
            ? ['returned', 'partially_returned', 'replaced']
            : ['progress', 'fulfilled'];

        if (in_array($eventType, $increaseEvents, true)) {
            if ($quantity === null) {
                throw ValidationException::withMessages(['quantity' => 'Enter the quantity being added.']);
            }

            $remaining = Decimal::add($current ?? Decimal::normalise('0'), $quantity);
            $obligation->current_subject_quantity = $remaining;
            $obligation->status = 'active';
            $obligation->settled_at = null;
            $obligation->save();

            return [$quantity, 'increase'];
        }

        if (! in_array($eventType, $decreaseEvents, true)) {
            return [$quantity, null];
        }

        if ($kind === ObligationKind::Service && $current === null && $eventType === 'progress' && $quantity !== null) {
            throw ValidationException::withMessages([
                'quantity' => 'Add an opening quantity before recording measurable progress.',
            ]);
        }

        if ($kind === ObligationKind::Service && $current === null && $eventType === 'fulfilled') {
            $obligation->status = 'settled';
            $obligation->settled_at = now();
            $obligation->save();

            return [null, null];
        }

        if ($eventType === 'fulfilled' && $quantity === null && $current !== null) {
            $quantity = $current;
        }

        if ($quantity === null) {
            if ($eventType === 'progress') {
                return [null, null];
            }

            throw ValidationException::withMessages(['quantity' => 'Enter the quantity completed or returned.']);
        }

        if ($current !== null && Decimal::compare($quantity, $current) === 1) {
            throw ValidationException::withMessages([
                'quantity' => 'The quantity cannot be greater than the outstanding quantity.',
            ]);
        }

        $remaining = $current === null ? Decimal::normalise('0') : Decimal::subtract($current, $quantity);
        $obligation->current_subject_quantity = $remaining;

        if (Decimal::compare($remaining, Decimal::normalise('0')) === 0) {
            $obligation->status = 'settled';
            $obligation->settled_at = now();
        }

        $obligation->save();

        return [$quantity, 'decrease'];
    }

    /** @return numeric-string|null */
    private function normaliseQuantity(?string $quantity, QuantityMode $mode): ?string
    {
        if ($quantity === null || trim($quantity) === '') {
            return null;
        }

        try {
            return Quantity::normalise($quantity, $mode);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }
    }

    /** @return numeric-string|null */
    private function decimalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return preg_match('/^\d{1,16}(?:\.\d{1,4})?$/D', $value) === 1 ? Decimal::normalise($value) : null;
    }
}
