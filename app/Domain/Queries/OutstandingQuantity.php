<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MovementStatus;
use App\Models\Obligation;
use Illuminate\Support\Facades\DB;
use LogicException;

final class OutstandingQuantity
{
    /** @return array{total: numeric-string, returned: numeric-string, remaining: numeric-string} */
    public function forObligation(Obligation $obligation): array
    {
        $obligation->loadMissing('quantitySubject');

        if ($obligation->quantitySubject === null) {
            throw new LogicException('Quantity obligations must have a quantity subject.');
        }

        $total = $this->numericQuantity((string) $obligation->quantitySubject->total);
        $returned = '0.0000';
        $returnedQuantities = DB::table('quantity_returns')
            ->where('obligation_id', $obligation->getKey())
            ->where('status', MovementStatus::Confirmed->value)
            ->pluck('quantity');
        foreach ($returnedQuantities as $quantity) {
            $returned = bcadd($returned, $this->numericQuantity((string) $quantity), 4);
        }

        return [
            'total' => $total,
            'returned' => $returned,
            'remaining' => bcsub($total, $returned, 4),
        ];
    }

    /** @return numeric-string */
    private function numericQuantity(string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw new LogicException('Quantity values must be numeric.');
        }

        return $quantity;
    }
}
