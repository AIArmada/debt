<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => 'required|url|max:2000', 'keys' => 'nullable|array', 'keys.p256dh' => 'nullable|string|max:500', 'keys.auth' => 'nullable|string|max:500']);
        $subscription = PushSubscription::query()->updateOrCreate(['user_id' => $request->user()->getKey(), 'endpoint' => $validated['endpoint']], ['public_key' => data_get($validated, 'keys.p256dh'), 'auth_token' => data_get($validated, 'keys.auth'), 'content_encoding' => 'aes128gcm', 'revoked_at' => null]);

        return response()->json(['id' => $subscription->getKey(), 'subscribed' => true], 201);
    }

    public function destroy(Request $request, PushSubscription $subscription): JsonResponse
    {
        abort_unless($subscription->user_id === $request->user()->getKey(), 403);
        $subscription->update(['revoked_at' => now()]);

        return response()->json(['subscribed' => false]);
    }
}
