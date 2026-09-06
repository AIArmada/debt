<?php

namespace App\Models;

use Database\Factories\CommitmentSubjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $completed_at
 */
class CommitmentSubject extends Model
{
    /** @use HasFactory<CommitmentSubjectFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $primaryKey = 'obligation_id';

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['obligation_id', 'done_criteria', 'completed_at', 'completion_note'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Obligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Obligation::class, 'obligation_id');
    }
}
