<?php

namespace App\Domain\Movements;

use App\Domain\Enums\MovementStatus;
use App\Models\Instalment;
use App\Models\InstalmentAllocation;
use App\Models\MoneyMovement;

final class InstalmentAllocator
{
    public function allocateConfirmedReduction(MoneyMovement $movement, int $balanceBefore): void
    {
        $movement->loadMissing('obligation');
        $obligation = $movement->obligation;
        if ($movement->status !== MovementStatus::Confirmed || ! $movement->entry->isSettlement() || $balanceBefore === 0) {
            return;
        }

        $effect = (int) ($movement->calculation['signed_effect'] ?? $movement->entry->signedEffect($obligation->direction));
        if (($balanceBefore > 0 && $effect >= 0) || ($balanceBefore < 0 && $effect <= 0)) {
            return;
        }

        $remaining = (int) $movement->amount_minor;
        $instalments = Instalment::query()
            ->where('obligation_id', $obligation->getKey())
            ->where('currency', $movement->currency)
            ->whereColumn('paid_minor', '<', 'amount_minor')
            ->orderBy('sequence')
            ->lockForUpdate()
            ->get();

        // Instalments track combined principal and charges; BalanceBreakdown separately applies allocation_order.
        foreach ($instalments as $instalment) {
            if ($remaining <= 0) {
                break;
            }
            $amount = min($remaining, $instalment->amount_minor - $instalment->paid_minor);
            if ($amount <= 0) {
                continue;
            }

            $instalment->increment('paid_minor', $amount);
            $movement->instalmentAllocations()->create([
                'instalment_id' => $instalment->getKey(),
                'amount_minor' => $amount,
            ]);
            $remaining -= $amount;
        }
    }

    public function reverseMovementAllocations(MoneyMovement $movement): void
    {
        $allocations = InstalmentAllocation::query()
            ->where('money_movement_id', $movement->getKey())
            ->orderBy('instalment_id')
            ->lockForUpdate()
            ->get();
        if ($allocations->isEmpty()) {
            return;
        }

        $instalments = Instalment::query()
            ->whereKey($allocations->pluck('instalment_id')->unique()->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        foreach ($allocations as $allocation) {
            $instalment = $instalments->get($allocation->instalment_id);
            if ($instalment instanceof Instalment) {
                $instalment->decrement('paid_minor', (int) $allocation->amount_minor);
            }
            $allocation->delete();
        }
    }
}
