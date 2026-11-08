<?php

namespace App\Features\PromiseTracker\Models;

use Database\Factories\ObligationShareFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationShare extends Model
{
    protected $table = 'obligation_shares';

    /** @use HasFactory<ObligationShareFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['obligation_id', 'party_id', 'share_bps'];

    protected function casts(): array
    {
        return ['share_bps' => 'integer'];
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
