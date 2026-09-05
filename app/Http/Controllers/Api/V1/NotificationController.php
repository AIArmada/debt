<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 20), 1), 50);
        $notifications = $request->user()->notifications()
            ->select(['id', 'notifiable_type', 'notifiable_id', 'type', 'data', 'read_at', 'created_at'])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $notifications->map(fn ($notification): array => $this->payload($notification))->values(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    public function markAsRead(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['data' => $this->payload($item->fresh())]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => $count]]);
    }

    /** @return array<string, mixed> */
    private function payload(mixed $notification): array
    {
        return [
            'id' => $notification->getKey(),
            'type' => $notification->type,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}
