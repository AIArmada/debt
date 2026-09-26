<div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6" data-test="notification-inbox">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <div class="app-eyebrow">Your inbox</div>
            <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Notifications</flux:heading>
            <flux:text class="mt-2">Reminder delivery for the profiles and promises you can act on.</flux:text>
        </div>
        @if ($notifications->contains(fn (array $notification): bool => ! $notification['is_read']))
            <flux:button wire:click="markAllRead" wire:loading.attr="disabled" variant="ghost">Mark all read</flux:button>
        @endif
    </div>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($notifications as $notification)
                <article wire:key="notification-{{ $notification['id'] }}" class="flex flex-col gap-4 px-5 py-5 {{ $notification['is_read'] ? '' : 'bg-emerald-50/40 dark:bg-emerald-950/15' }}">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div>
                            <div class="font-semibold">{{ $notification['title'] }}</div>
                            <div class="mt-1 text-sm text-zinc-500">{{ $notification['profile_name'] }} · {{ $notification['age'] }}</div>
                        </div>
                        @if (! $notification['is_read'])
                            <span class="text-xs font-semibold uppercase tracking-wide text-emerald-700">New</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                        @foreach ($notification['amounts'] as $amount)
                            <span class="font-semibold">{{ $amount }}</span>
                        @endforeach
                        @if ($notification['direction'])<span>{{ $notification['direction'] }}</span>@endif
                        @if ($notification['due_on'])<span>Due {{ $notification['due_on'] }}</span>@endif
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:button wire:click="openPromise('{{ $notification['id'] }}')" wire:loading.attr="disabled" variant="primary">Open promise</flux:button>
                        <button type="button" wire:click="dismiss('{{ $notification['id'] }}')" wire:confirm="Dismiss this reminder?" wire:loading.attr="disabled" class="text-sm font-semibold text-red-700 hover:underline">Dismiss</button>
                        <button type="button" wire:click="snooze('{{ $notification['id'] }}')" wire:loading.attr="disabled" class="text-sm font-semibold text-zinc-600 hover:underline dark:text-zinc-300">Snooze</button>
                        @if (! $notification['is_read'])
                            <button type="button" wire:click="markRead('{{ $notification['id'] }}')" wire:loading.attr="disabled" class="text-sm font-semibold text-zinc-600 hover:underline dark:text-zinc-300">Mark read</button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="px-5 py-12 text-center">
                    <flux:heading size="lg">You’re all caught up</flux:heading>
                    <flux:text class="mt-2">Due reminders will appear here when they are delivered.</flux:text>
                </div>
            @endforelse
        </div>
    </section>
</div>
