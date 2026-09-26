@props(['unreadCount' => 0])

<a href="{{ route('notifications.index') }}" wire:navigate aria-label="Notifications" data-test="notification-bell" class="relative inline-flex size-9 items-center justify-center rounded-lg text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:hover:text-white">
    <flux:icon name="bell" class="size-5" />
    @if ($unreadCount > 0)
        <span class="absolute -right-0.5 -top-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-emerald-600 px-1 text-[10px] font-bold leading-4 text-white" aria-label="{{ $unreadCount }} unread notifications">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    @endif
</a>
