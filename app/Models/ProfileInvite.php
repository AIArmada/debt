<?php

namespace App\Models;

use App\Domain\Enums\MemberRole;
use Database\Factories\ProfileInviteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property MemberRole $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class ProfileInvite extends Model
{
    /** @use HasFactory<ProfileInviteFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['profile_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['role' => MemberRole::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
