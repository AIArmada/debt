<?php

namespace App\Models;

use App\Domain\Enums\MemberRole;
use Database\Factories\ProfileMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property MemberRole $role */
class ProfileMember extends Model
{
    /** @use HasFactory<ProfileMemberFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['role', 'accepted_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['role' => MemberRole::class, 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
