<?php

namespace App\Models;

use App\Domain\Enums\ImportRowStatus;
use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ImportRowStatus $status
 * @property string|null $suggested_entry
 * @property Carbon $occurred_on
 */
class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['import_batch_id', 'occurred_on', 'amount_minor', 'currency', 'description', 'status', 'suggested_obligation_id', 'suggested_entry', 'matched_movement_id'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'amount_minor' => 'integer', 'status' => ImportRowStatus::class];
    }

    /** @return BelongsTo<ImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    /** @return BelongsTo<Obligation, $this> */
    public function suggestedObligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'suggested_obligation_id');
    }

    /** @return BelongsTo<MoneyMovement, $this> */
    public function matchedMovement(): BelongsTo
    {
        return $this->belongsTo(MoneyMovement::class, 'matched_movement_id');
    }
}
