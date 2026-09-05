<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\Record;
use App\Models\RecordParty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Record */
class RecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'profile_id' => $this->profile_id,
            'title' => $this->title,
            'description' => $this->description,
            'sensitivity' => $this->sensitivity,
            'is_archived' => $this->is_archived,
            'state' => $this->stateLabel(),
            'parties' => $this->whenLoaded('partyLinks', fn (): array => $this->partyLinks->map(fn (RecordParty $link): array => [
                'id' => $link->party_id,
                'name' => $link->party?->preferred_name,
                'kind' => $link->party?->kind,
                'role' => $link->role,
                'is_primary' => $link->is_primary,
                'status' => $link->status,
            ])->values()->all()),
            'obligations' => ObligationResource::collection($this->whenLoaded('obligations')),
            'documents' => $this->whenLoaded('documents', fn (): array => $this->documents->map(fn (Document $document): array => [
                'id' => $document->getKey(),
                'evidence_type' => $document->evidence_type,
                'title' => $document->title,
                'category' => $document->category,
                'verification_status' => $document->verification_status,
                'captured_on' => $document->captured_on?->toDateString(),
            ])->values()->all()),
        ];
    }
}
