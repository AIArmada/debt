<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'profile_id', 'actor_user_id', 'auditable_type', 'auditable_id', 'action',
        'ip_address', 'user_agent', 'before', 'after', 'metadata', 'occurred_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Audit logs are append-only.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Audit logs cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<FinancialProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(FinancialProfile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
