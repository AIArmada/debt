<?php

namespace App\Http\Middleware;

use App\Domain\Enums\ApiTokenAbility;
use App\Models\ApiToken;
use App\Services\ProfileAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class HandleApiToken
{
    public function __construct(private readonly ProfileAccess $profileAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        $token = $plainToken === null ? null : ApiToken::query()->with(['user', 'profile'])->where('token_hash', hash('sha256', $plainToken))->first();

        if ($token === null) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        if (! $this->profileAccess->role($token->user, $token->profile)) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $ability = $request->isMethodSafe() ? ApiTokenAbility::Read : ApiTokenAbility::Write;
        if (! $token->allows($ability)) {
            return response()->json(['message' => "This token is not allowed to {$ability->value}."], 403);
        }

        $token->forceFill(['last_used_at' => now()])->save();
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);
        $request->attributes->set('api_profile', $token->profile);

        return $next($request);
    }
}
