<?php

namespace App\Http\Resources;

use App\Models\ObligationDeliveryInstruction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ObligationDeliveryInstruction */
class DeliveryInstructionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'obligation_id' => $this->obligation_id,
            'address_id' => $this->address_id,
            'recipient_party_id' => $this->recipient_party_id,
            'method' => $this->method,
            'label' => $this->label,
            'instructions' => $this->instructions,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'shown_snapshot' => $this->shown_snapshot,
        ];
    }
}
