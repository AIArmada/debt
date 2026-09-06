<?php

namespace App\Models;

use App\Domain\Enums\ApiTokenAbility;
use Database\Factories\ApiTokenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property list<string> $abilities
 * @property Carbon|null $last_used_at
 */
class ApiToken extends Model
{
    /** @use HasFactory<ApiTokenFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['profile_id', 'user_id', 'name', 'token_hash', 'abilities', 'last_used_at'];

    protected function casts(): array
    {
        return ['abilities' => 'array', 'last_used_at' => 'datetime'];
    }

    public function allows(ApiTokenAbility $ability): bool
    {
        return in_array($ability->value, $this->abilities ?? [], true);
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
