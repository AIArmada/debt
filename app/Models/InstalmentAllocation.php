<?php

namespace App\Models;

use Database\Factories\InstalmentAllocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalmentAllocation extends Model
{
    /** @use HasFactory<InstalmentAllocationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['money_movement_id', 'instalment_id', 'amount_minor'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    /** @return BelongsTo<MoneyMovement, $this> */
    public function moneyMovement(): BelongsTo
    {
        return $this->belongsTo(MoneyMovement::class);
    }

    /** @return BelongsTo<Instalment, $this> */
    public function instalment(): BelongsTo
    {
        return $this->belongsTo(Instalment::class);
    }
}
