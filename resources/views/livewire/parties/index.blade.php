<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <div class="app-eyebrow">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ __('app.people_title') }}</flux:heading>
        <flux:text class="mt-2 leading-6">{{ __('app.people_intro') }}</flux:text>
    </div>

    @if (collect($nettingSuggestions)->flatten(1)->isNotEmpty())
        <section class="app-card space-y-4 rounded-2xl p-5">
            <div>
                <flux:heading size="lg">{{ __('app.netting_suggestions') }}</flux:heading>
                <flux:text class="mt-1">{{ __('app.netting_advisory') }}</flux:text>
            </div>
            @foreach ($nettingSuggestions as $currency => $suggestions)
                @if ($suggestions !== [])
                    <div wire:key="netting-{{ $currency }}" class="space-y-2">
                        <div class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $currency }}</div>
                        @foreach ($suggestions as $suggestion)
                            <div wire:key="netting-{{ $currency }}-{{ $loop->index }}" class="rounded-xl bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-900">
                                {{ $suggestion['from_party']->display_name }} → {{ $suggestion['to_party']->display_name }} · {{ \App\Domain\Money\Money::display($suggestion['amount_minor'], $currency) }}
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    @if (session('party-created'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('party-created') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <flux:heading size="lg">{{ __('app.your_people') }}</flux:heading>
                <div class="mt-3 flex gap-2 text-sm">
                    <button type="button" wire:click="$set('statusFilter', '{{ \App\Domain\Enums\PartyStatus::Active->value }}')" wire:loading.attr="disabled" class="{{ $statusFilter === \App\Domain\Enums\PartyStatus::Active->value ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">{{ __('app.active') }}</button>
                    <button type="button" wire:click="$set('statusFilter', '{{ \App\Domain\Enums\PartyStatus::Archived->value }}')" wire:loading.attr="disabled" class="{{ $statusFilter === \App\Domain\Enums\PartyStatus::Archived->value ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">{{ __('app.archived') }}</button>
                    <button type="button" wire:click="$set('statusFilter', 'all')" wire:loading.attr="disabled" class="{{ $statusFilter === 'all' ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">{{ __('app.all') }}</button>
                </div>
            </div>
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($parties as $party)
                    <a href="{{ route('people.show', [$profile, $party]) }}" wire:navigate class="block px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                        <div class="font-medium">{{ $party->displayName() }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ __('app.party_kinds.'.$party->kind->value) }}</div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-sm text-zinc-500">{{ __('app.people_empty') }}</div>
                @endforelse
            </div>
        </section>

        <section class="app-card rounded-2xl p-5">
            <flux:heading size="lg">{{ __('app.add_someone') }}</flux:heading>
            <form wire:submit="save" class="mt-5 space-y-4">
                <flux:input wire:model="name" :label="__('app.name')" :placeholder="__('promises.person_placeholder')" required autofocus />
                <flux:select wire:model="kind" :label="__('app.type')">
                    <flux:select.option value="individual">{{ __('app.party_kinds.individual') }}</flux:select.option>
                    <flux:select.option value="organization">{{ __('app.party_kinds.organization') }}</flux:select.option>
                    <flux:select.option value="group">{{ __('app.party_kinds.group') }}</flux:select.option>
                </flux:select>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full">{{ __('app.save_person') }}</flux:button>
            </form>
        </section>
    </div>
</div>
