<div wire:poll.30s>
    <a href="{{ route('notifications.settings') }}" wire:navigate class="relative inline-flex size-10 items-center justify-center rounded-xl text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100" aria-label="Notifications">
        <flux:icon name="bell" class="size-5" />
        @if ($unreadCount > 0)
            <span class="absolute right-1.5 top-1.5 inline-flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-4 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </a>
</div>
