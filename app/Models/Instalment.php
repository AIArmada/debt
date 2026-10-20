<?php

namespace App\Models;

use App\Domain\Enums\InstalmentStatus;
use Database\Factories\InstalmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $due_on
 * @property int $sequence
 * @property int $amount_minor
 * @property int $paid_minor
 * @property string $currency
 */
class Instalment extends Model
{
    /** @use HasFactory<InstalmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['obligation_id', 'sequence', 'due_on', 'amount_minor', 'currency', 'paid_minor'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_on' => 'date',
            'amount_minor' => 'integer',
            'paid_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return HasMany<InstalmentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InstalmentAllocation::class);
    }

    public function status(): InstalmentStatus
    {
        if ($this->paid_minor >= $this->amount_minor) {
            return InstalmentStatus::Settled;
        }

        return $this->due_on->isBefore(today()) ? InstalmentStatus::Overdue : InstalmentStatus::Pending;
    }
}
