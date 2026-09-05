<?php

namespace App\Http\Resources;

use App\Domain\Money\MoneyAmount;
use App\Models\CollectionSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CollectionSchedule */
class CollectionScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'obligation_id' => $this->obligation_id,
            'collection_account_id' => $this->collection_account_id,
            'mode' => $this->mode,
            'status' => $this->status,
            'amount' => MoneyAmount::majorInput((int) $this->amount, (string) $this->currency),
            'amount_minor' => $this->amount,
            'currency' => $this->currency,
            'frequency' => $this->frequency,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'next_due_on' => $this->next_due_on?->toDateString(),
            'collection_method' => $this->collection_method,
            'grace_days' => $this->grace_days,
            'note' => $this->note,
        ];
    }
}
