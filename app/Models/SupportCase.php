<?php

namespace App\Models;

use App\Domain\Enums\SupportAccessGrantStatus;
use App\Domain\Enums\SupportCaseStatus;
use Database\Factories\SupportCaseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property SupportCaseStatus $status */
class SupportCase extends Model
{
    /** @use HasFactory<SupportCaseFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['profile_id', 'opened_by', 'subject', 'reason', 'status', 'closed_at', 'closed_by'];

    protected function casts(): array
    {
        return ['status' => SupportCaseStatus::class, 'closed_at' => 'datetime'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<SupportAccessGrant, $this> */
    public function grants(): HasMany
    {
        return $this->hasMany(SupportAccessGrant::class, 'case_id');
    }

    /** @return HasMany<SupportAuditEntry, $this> */
    public function auditEntries(): HasMany
    {
        return $this->hasMany(SupportAuditEntry::class, 'case_id');
    }

    public function isOpen(): bool
    {
        return $this->status === SupportCaseStatus::Open;
    }

    public function activeGrant(): ?SupportAccessGrant
    {
        if (! $this->isOpen()) {
            return null;
        }

        return $this->grants()
            ->where('status', SupportAccessGrantStatus::Approved->value)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where('expires_at', '>', now())
            ->latest('starts_at')
            ->first();
    }
}
