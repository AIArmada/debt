<div class="mx-auto w-full max-w-2xl" wire:poll.10s>
    <div class="mb-7">
        <div class="flex items-start justify-between gap-4"><div class="flex items-start gap-4"><span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="bell" class="size-6" /></span><div><div class="app-eyebrow">Stay informed</div><flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Notifications</flux:heading><flux:text class="mt-2 leading-6">Important record activity appears here and is also available through the API.</flux:text></div></div><x-status-badge tone="success" :label="$unreadCount.' unread'" /></div>
    </div>

    @if (session('notification-settings-saved'))
        <div class="mb-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">{{ session('notification-settings-saved') }}</div>
    @endif

    <form wire:submit="save" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:checkbox wire:model="inAppEnabled" label="In-app notifications" />
        <flux:checkbox wire:model="emailEnabled" label="Email notifications" />
        <flux:checkbox wire:model="pushEnabled" label="Web push notifications" />
        <flux:checkbox wire:model="genericPush" label="Keep push text generic (recommended)" />
        <flux:input wire:model="dueReminderDays" type="number" min="0" max="30" label="Remind me this many days before due" />
        <flux:button type="submit" variant="primary">Save preferences</flux:button>
    </form>

    @if (filled(config('services.push.public_key')))
        <section class="mt-5 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg">This browser</flux:heading>
            <flux:text class="mt-1 text-sm">Enable push on this browser, then turn on Web push notifications above.</flux:text>
            <button type="button" data-push-subscribe data-push-public-key="{{ config('services.push.public_key') }}" data-push-subscribe-url="{{ route('push-subscriptions.store') }}" class="mt-4 inline-flex items-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-900">Enable this browser</button>
        </section>
    @endif

    <section class="mt-5 rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700"><flux:heading size="lg">Recent notifications</flux:heading></div>
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @forelse ($notifications as $notification)
                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <div class="font-medium">{{ $notification->data['title'] ?? 'Private reminder' }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $notification->created_at->format('d M Y, H:i') }}</div>
                    </div>
                    @if ($notification->read_at === null)
                        <flux:button size="sm" variant="ghost" wire:click="markAsRead('{{ $notification->id }}')">Mark read</flux:button>
                    @else
                        <x-status-badge tone="neutral" label="Read" />
                    @endif
                </div>
            @empty
                <div class="px-5 py-8 text-sm text-zinc-500">No notifications yet.</div>
            @endforelse
        </div>
    </section>

    <div class="mt-5 rounded-lg bg-zinc-50 p-4 text-sm text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-300">Email delivery uses your configured mail provider. Web push delivery uses the configured push gateway and requires a browser subscription; no notification includes a balance or party name by default.</div>
</div>
