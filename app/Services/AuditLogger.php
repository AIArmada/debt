<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?FinancialProfile $profile,
        ?User $actor,
        string $auditableType,
        string $auditableId,
        string $action,
        ?array $before = null,
        ?array $after = null,
        array $metadata = [],
    ): AuditLog {
        $request = app()->bound('request') ? app(Request::class) : null;
        $authenticatedUser = Auth::user();
        $actor ??= $authenticatedUser instanceof User ? $authenticatedUser : null;

        return AuditLog::create([
            'profile_id' => $profile?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
