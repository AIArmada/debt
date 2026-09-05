<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationParty extends Model
{
    use HasUuids;

    protected $fillable = ['obligation_id', 'party_id', 'created_by_user_id', 'role', 'share_basis', 'share_percent', 'share_amount', 'share_currency', 'status', 'notes', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return ['share_percent' => 'decimal:8', 'share_amount' => 'integer', 'valid_from' => 'date', 'valid_to' => 'date'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
