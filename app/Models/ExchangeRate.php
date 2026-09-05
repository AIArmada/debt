<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasUuids;

    protected $fillable = ['profile_id', 'from_currency', 'to_currency', 'rate', 'source', 'effective_on'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:10', 'effective_on' => 'date'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }
}
