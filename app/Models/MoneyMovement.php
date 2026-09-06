<?php

namespace App\Models;

use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use Database\Factories\MoneyMovementFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property MoneyEntry $entry
 * @property MovementStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property Carbon $occurred_on
 * @property-read Collection<int, Attachment> $attachments
 */
class MoneyMovement extends Model
{
    /** @use HasFactory<MoneyMovementFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'obligation_id', 'entry', 'amount_minor', 'currency', 'occurred_on',
        'status', 'void_reason', 'note', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'entry' => MoneyEntry::class,
            'status' => MovementStatus::class,
            'amount_minor' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function void(string $reason): void
    {
        $this->forceFill([
            'status' => MovementStatus::Voided,
            'void_reason' => $reason,
        ])->save();
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
