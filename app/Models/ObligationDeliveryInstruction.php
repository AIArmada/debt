<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationDeliveryInstruction extends Model
{
    use HasUuids;

    protected $fillable = [
        'obligation_id', 'address_id', 'recipient_party_id', 'created_by_user_id', 'method', 'label',
        'instructions', 'status', 'verification_status', 'verified_at', 'superseded_at', 'shown_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'superseded_at' => 'datetime',
            'shown_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<PartyAddress, $this> */
    public function address(): BelongsTo
    {
        return $this->belongsTo(PartyAddress::class, 'address_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function recipientParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'recipient_party_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
