<?php

namespace App\Models;

use App\Domain\Enums\MovementStatus;
use Database\Factories\QuantityReturnFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $quantity
 * @property MovementStatus $status
 * @property Carbon $occurred_on
 * @property-read Collection<int, Attachment> $attachments
 */
class QuantityReturn extends Model
{
    /** @use HasFactory<QuantityReturnFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['obligation_id', 'quantity', 'occurred_on', 'note', 'status', 'void_reason', 'recorded_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'occurred_on' => 'date', 'status' => MovementStatus::class];
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
