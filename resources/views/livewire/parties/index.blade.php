<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <div class="app-eyebrow">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">People</flux:heading>
        <flux:text class="mt-2 leading-6">Keep the people connected to your promises in one place.</flux:text>
    </div>

    @if (session('party-created'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('party-created') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <flux:heading size="lg">Your people</flux:heading>
                <div class="mt-3 flex gap-2 text-sm">
                    <button type="button" wire:click="$set('statusFilter', '{{ \App\Domain\Enums\PartyStatus::Active->value }}')" wire:loading.attr="disabled" class="{{ $statusFilter === \App\Domain\Enums\PartyStatus::Active->value ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">Active</button>
                    <button type="button" wire:click="$set('statusFilter', '{{ \App\Domain\Enums\PartyStatus::Archived->value }}')" wire:loading.attr="disabled" class="{{ $statusFilter === \App\Domain\Enums\PartyStatus::Archived->value ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">Archived</button>
                    <button type="button" wire:click="$set('statusFilter', 'all')" wire:loading.attr="disabled" class="{{ $statusFilter === 'all' ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">All</button>
                </div>
            </div>
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($parties as $party)
                    <a href="{{ route('people.show', [$profile, $party]) }}" wire:navigate class="block px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                        <div class="font-medium">{{ $party->displayName() }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $party->kind->value }}</div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-sm text-zinc-500">People will appear here as you add promises.</div>
                @endforelse
            </div>
        </section>

        <section class="app-card rounded-2xl p-5">
            <flux:heading size="lg">Add someone</flux:heading>
            <form wire:submit="save" class="mt-5 space-y-4">
                <flux:input wire:model="name" label="Name" placeholder="e.g. Ali" required autofocus />
                <flux:select wire:model="kind" label="Type">
                    <flux:select.option value="individual">Person</flux:select.option>
                    <flux:select.option value="organization">Organization</flux:select.option>
                    <flux:select.option value="group">Group or household</flux:select.option>
                </flux:select>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full">Save person</flux:button>
            </form>
        </section>
    </div>
</div>
