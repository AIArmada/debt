<?php

namespace App\Models;

use App\Domain\Enums\Direction;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\SubjectType;
use Database\Factories\ObligationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property Direction $direction
 * @property ObligationStatus $status
 * @property SubjectType $subject_type
 * @property Carbon|null $due_on
 * @property-read Collection<int, MoneyMovement> $moneyMovements
 * @property-read Collection<int, QuantityReturn> $quantityReturns
 * @property-read Collection<int, Attachment> $attachments
 * @property-read Collection<int, Reminder> $reminders
 */
class Obligation extends Model
{
    /** @use HasFactory<ObligationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['record_id', 'direction', 'title', 'status', 'due_on', 'subject_type'];

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'status' => ObligationStatus::class,
            'subject_type' => SubjectType::class,
            'due_on' => 'date',
        ];
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return HasOne<MoneySubject, $this> */
    public function moneySubject(): HasOne
    {
        return $this->hasOne(MoneySubject::class);
    }

    /** @return HasOne<QuantitySubject, $this> */
    public function quantitySubject(): HasOne
    {
        return $this->hasOne(QuantitySubject::class);
    }

    /** @return HasOne<CommitmentSubject, $this> */
    public function commitmentSubject(): HasOne
    {
        return $this->hasOne(CommitmentSubject::class);
    }

    /** @return HasMany<MoneyMovement, $this> */
    public function moneyMovements(): HasMany
    {
        return $this->hasMany(MoneyMovement::class)->orderByDesc('occurred_on')->orderByDesc('created_at');
    }

    /** @return HasMany<QuantityReturn, $this> */
    public function quantityReturns(): HasMany
    {
        return $this->hasMany(QuantityReturn::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }
}
