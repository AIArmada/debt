<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-7">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="rectangle-stack" class="size-6" /></span>
            <div>
                <span class="app-eyebrow">{{ $profile->name }}</span>
                <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Records</flux:heading>
                <flux:text class="mt-2 max-w-2xl leading-6">One arrangement can hold several kinds of obligation, each with its own balance, progress, participants, and evidence.</flux:text>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <flux:select wire:model.live="profileId" class="w-48" aria-label="Financial profile">
                @foreach ($profiles as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach
            </flux:select>
            <flux:button variant="primary" :href="route('records.create')" wire:navigate><flux:icon name="plus" class="size-4" /> New record</flux:button>
        </div>
    </div>

    <div class="flex flex-col gap-2">
        <span class="app-eyebrow">Filter by position</span>
        <div class="flex max-w-full flex-wrap gap-1 rounded-xl border border-zinc-200/80 bg-white/70 p-1 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/70">
            @foreach (['all' => 'All', 'payable' => 'I owe / must do', 'receivable' => 'Owed to me'] as $key => $label)
                <flux:button class="rounded-lg" wire:click="$set('direction', '{{ $key }}')" size="sm" :variant="$direction === $key ? 'primary' : 'ghost'">{{ $label }}</flux:button>
            @endforeach
        </div>
    </div>
    <div class="flex flex-col gap-2">
        <span class="app-eyebrow">Obligation kind</span>
        <div class="flex max-w-full flex-wrap gap-1 rounded-xl border border-zinc-200/80 bg-white/70 p-1 shadow-sm dark:border-zinc-700/80 dark:bg-zinc-900/70">
            @foreach (['all' => 'All kinds', 'money' => 'Money', 'asset' => 'Assets', 'service' => 'Services / time', 'action' => 'Actions'] as $key => $label)
                <flux:button class="rounded-lg" wire:click="$set('kind', '{{ $key }}')" size="sm" :variant="$kind === $key ? 'primary' : 'ghost'">{{ $label }}</flux:button>
            @endforeach
        </div>
    </div>

    <section class="grid gap-4 lg:grid-cols-2">
        @forelse ($records as $record)
            <a wire:key="record-card-{{ $record->id }}" wire:navigate href="{{ route('records.show', $record) }}" class="app-card group rounded-2xl p-5 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md dark:hover:border-emerald-700">
                @php($primaryParty = $record->partyLinks->first(fn ($link) => $link->is_primary && $link->role === 'other_party')?->party)
                @php($recordStateTone = match ($record->stateLabel()) { 'All obligations settled' => 'success', 'Partly resolved' => 'warning', 'Open' => 'info', default => 'neutral' })
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-lg font-semibold tracking-tight text-zinc-900 group-hover:text-accent-content dark:text-zinc-100 dark:group-hover:text-accent-content">{{ $record->title }}</div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $primaryParty?->preferred_name ?? 'No party recorded yet' }} · {{ $record->obligations->count() }} obligation{{ $record->obligations->count() === 1 ? '' : 's' }}</div>
                    </div>
                    <x-status-badge :tone="$recordStateTone" :label="$record->stateLabel()" />
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($record->obligations as $obligation)
                        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50/70 px-2.5 py-2 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">
                            <x-obligation-badge :kind="$obligation->obligation_kind" :label="$obligation->kindLabel()" />
                            <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $obligation->title }}</span>
                            <span class="text-zinc-500">{{ $obligation->categoryLabel() }}</span>
                            @if ($obligation->obligation_kind === 'money')
                                <x-direction-badge :direction="$obligation->currentPositionDirection()" :reversed="$obligation->isPositionReversed()" :label="$obligation->isPositionReversed() ? 'Position changed — review' : $obligation->effectiveDirectionLabel()" />
                                <span>{{ \App\Domain\Money\MoneyAmount::format($obligation->currentPositionAmount(), $obligation->currency) }}</span>
                            @elseif ($obligation->isQuantityBased())
                                <x-status-badge tone="info" label="In progress" />
                                <span>{{ \App\Domain\Money\Decimal::display($obligation->current_subject_quantity) }} {{ $obligation->subject_unit }} outstanding</span>
                            @else
                                <x-status-badge :tone="$obligation->status === 'settled' || $obligation->status === 'fulfilled' ? 'success' : 'info'" :label="$obligation->status === 'settled' || $obligation->status === 'fulfilled' ? 'Completed' : 'In progress'" />
                            @endif
                        </div>
                        @if ($obligation->obligation_kind === 'money' && $obligation->isPositionReversed())
                            <x-status-badge tone="warning" label="Direction changed" />
                        @endif
                    @endforeach
                </div>
                @if ($record->description)<p class="mt-4 line-clamp-2 text-sm text-zinc-500">{{ $record->description }}</p>@endif
            </a>
        @empty
            <div class="app-card rounded-2xl border-dashed px-6 py-14 text-center lg:col-span-2">
                <span class="app-icon-badge mx-auto size-11"><flux:icon name="magnifying-glass" class="size-5" /></span>
                <flux:heading size="lg" class="mt-3">No records match this view</flux:heading>
                <flux:text class="mt-2">Create one arrangement and add as many independent obligations and participants as the situation needs.</flux:text>
                <flux:button class="mt-5" variant="primary" :href="route('records.create')" wire:navigate><flux:icon name="plus" class="size-4" /> Create a record</flux:button>
            </div>
        @endforelse
    </section>
</div>
