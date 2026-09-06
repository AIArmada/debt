<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('people.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to people</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ $party->display_name }}</flux:heading>
        <flux:text class="mt-2">{{ ucfirst($party->kind->value) }} exposure across your promise records.</flux:text>
        @can('update', $party)
            <div class="mt-4">
                @if ($party->status === \App\Domain\Enums\PartyStatus::Active)
                    <flux:button wire:click="archive" wire:confirm="Archive this person? They must have no open promises." variant="ghost" wire:loading.attr="disabled">Archive person</flux:button>
                @else
                    <flux:button wire:click="restore" wire:confirm="Restore this person?" variant="ghost" wire:loading.attr="disabled">Restore person</flux:button>
                @endif
            </div>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-card rounded-2xl p-5"><flux:text>Open parts</flux:text><div class="mt-2 text-2xl font-semibold">{{ $exposure['open_count'] }}</div></div>
        <div class="app-card rounded-2xl p-5"><flux:text>Settled parts</flux:text><div class="mt-2 text-2xl font-semibold">{{ $exposure['settled_count'] }}</div></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-metric app-metric-primary rounded-2xl p-5">
            <flux:text>To pay</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold">@forelse ($exposure['to_pay'] as $currency => $amount)<div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
        </div>
        <div class="app-metric app-metric-secondary rounded-2xl p-5">
            <flux:text>To receive</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold text-cyan-800">@forelse ($exposure['to_receive'] as $currency => $amount)<div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
        </div>
    </div>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">Promise records</flux:heading></div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($records as $record)
                <a href="{{ route('promises.show', [$profile, $record]) }}" wire:navigate class="block px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                    <div class="font-medium">{{ $record->title }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ $record->obligations->count() }} {{ $record->obligations->count() === 1 ? 'part' : 'parts' }}</div>
                </a>
            @empty
                <div class="px-5 py-10 text-sm text-zinc-500">No active promise records.</div>
            @endforelse
        </div>
    </section>
</div>
