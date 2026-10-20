<?php

namespace App\Models;

use App\Domain\Enums\SupportAccessGrantStatus;
use Carbon\CarbonInterface;
use Database\Factories\SupportAccessGrantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property SupportAccessGrantStatus $status
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $revoked_at
 */
class SupportAccessGrant extends Model
{
    /** @use HasFactory<SupportAccessGrantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['case_id', 'requested_by', 'approved_by', 'status', 'starts_at', 'expires_at', 'revoked_at', 'revoked_by'];

    protected function casts(): array
    {
        return ['status' => SupportAccessGrantStatus::class, 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<SupportCase, $this> */
    public function supportCase(): BelongsTo
    {
        return $this->belongsTo(SupportCase::class, 'case_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->status === SupportAccessGrantStatus::Approved
            && $this->revoked_at === null
            && $this->starts_at !== null
            && $this->expires_at !== null
            && $this->starts_at->lessThanOrEqualTo(now())
            && $this->expires_at->greaterThan(now())
            && $this->supportCase()->where('status', 'open')->exists();
    }
}
