<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationPaymentInstruction extends Model
{
    use HasUuids;

    protected $fillable = ['obligation_id', 'payment_destination_id', 'beneficiary_party_id', 'payee_party_id', 'currency', 'reference', 'shown_snapshot', 'status', 'verified_at', 'superseded_at'];

    protected function casts(): array
    {
        return ['shown_snapshot' => 'array', 'verified_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<PartyPaymentDestination, $this> */
    public function paymentDestination(): BelongsTo
    {
        return $this->belongsTo(PartyPaymentDestination::class, 'payment_destination_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'beneficiary_party_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'payee_party_id');
    }
}
