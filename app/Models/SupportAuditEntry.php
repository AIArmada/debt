<?php

namespace App\Models;

use App\Services\SupportAuditLogger;
use Database\Factories\SupportAuditEntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class SupportAuditEntry extends Model
{
    /** @use HasFactory<SupportAuditEntryFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = ['actor_id', 'case_id', 'profile_id', 'action', 'subject_type', 'subject_id', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(static function (SupportAuditEntry $entry): void {
            if (! app(SupportAuditLogger::class)->isCreatingEntry($entry)) {
                throw new LogicException('Support audit entries can only be created through SupportAuditLogger.');
            }
        });

        static::updating(static function (): never {
            throw new LogicException('Support audit entries are immutable.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Support audit entries are immutable.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<SupportCase, $this> */
    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class, 'case_id');
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
