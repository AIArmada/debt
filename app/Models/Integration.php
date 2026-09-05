<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    use HasUuids;

    protected $fillable = ['profile_id', 'provider', 'type', 'status', 'credentials_encrypted', 'metadata', 'last_synced_at', 'revoked_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'last_synced_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return Attribute<?array<string, mixed>, ?array<string, mixed>> */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?array => isset($attributes['credentials_encrypted']) ? decrypt($attributes['credentials_encrypted']) : null,
            set: fn (?array $value): array => ['credentials_encrypted' => $value === null ? null : encrypt($value)],
        );
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }
}
