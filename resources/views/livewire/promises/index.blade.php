<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="app-eyebrow">{{ $profile->name }}</div>
            <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Promises</flux:heading>
            <flux:text class="mt-2 leading-6">A clear list of what you owe and what others owe you.</flux:text>
        </div>
        <a href="{{ route('promises.create', $profile) }}" wire:navigate class="app-primary-link inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold">
            <flux:icon name="plus" class="size-4" />
            New promise
        </a>
    </div>

    <div class="flex gap-3 text-sm">
        @foreach (\App\Domain\Enums\RecordArchiveFilter::cases() as $filter)
            <button type="button" wire:click="$set('statusFilter', '{{ $filter->value }}')" wire:loading.attr="disabled" class="{{ $statusFilter === $filter->value ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">{{ $filter->label() }} <span class="text-xs">({{ $filterCounts[$filter->value] ?? 0 }})</span></button>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-metric app-metric-primary rounded-2xl p-5">
            <flux:text>To pay</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold tracking-tight">
                @forelse ($totals['to_pay'] as $currency => $amount)
                    <div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>
                @empty
                    <div>—</div>
                @endforelse
            </div>
            <flux:text class="mt-2 text-sm">Grouped by currency</flux:text>
        </div>
        <div class="app-metric app-metric-secondary rounded-2xl p-5">
            <flux:text>To receive</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold tracking-tight text-cyan-800">
                @forelse ($totals['to_receive'] as $currency => $amount)
                    <div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>
                @empty
                    <div>—</div>
                @endforelse
            </div>
            <flux:text class="mt-2 text-sm">Grouped by currency</flux:text>
        </div>
    </div>

    <div class="relative">
        <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" placeholder="Search promises or people" aria-label="Search promises" />
    </div>

    @if ($dueSoon->isNotEmpty())
        <section class="app-card rounded-2xl p-5">
            <flux:heading size="lg">Due soon</flux:heading>
            <div class="mt-4 space-y-2">
                @foreach ($dueSoon as $dueRecord)
                    @foreach ($dueRecord->obligations as $dueObligation)
                        <a href="{{ route('promises.show', [$profile, $dueRecord]) }}" wire:navigate class="flex items-center justify-between gap-4 rounded-xl bg-amber-50 px-3 py-3 text-sm text-amber-950">
                            <span>{{ $dueRecord->title }}</span>
                            <span class="shrink-0">{{ $dueObligation->due_on?->format('d M Y') ?? 'Reminder' }}</span>
                        </a>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($records as $record)
                @php($party = $record->partyLinks->first()?->party)
                @php($obligation = $record->obligations->first())
                <a href="{{ route('promises.show', [$profile, $record]) }}" wire:navigate wire:key="record-{{ $record->id }}" class="flex flex-col gap-3 px-5 py-5 transition hover:bg-zinc-50/80 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-zinc-900/60">
                    <div class="min-w-0">
                        <div class="font-medium text-zinc-950 dark:text-zinc-100">{{ $party?->displayName() ?? $record->title }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                            <span>{{ $obligation?->direction?->label() ?? 'Promise' }}</span>
                            <span>· {{ $record->obligations->count() }} {{ $record->obligations->count() === 1 ? 'part' : 'parts' }}</span>
                            @if ($overdue[$record->id] ?? false)
                                <x-status-badge tone="danger" label="Overdue" />
                            @endif
                            @if ($obligation?->due_on)
                                <span>· Due {{ $obligation->due_on->format('d M Y') }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-left sm:text-right">
                        @forelse ($balances[$record->id] ?? [] as $position)
                            <div class="font-semibold">{{ \App\Domain\Money\Money::display($position['amount'], $position['currency']) }}</div>
                            <div class="text-xs text-zinc-500">{{ $position['label'] }} · {{ $position['currency'] }}</div>
                        @empty
                            <div class="font-semibold">{{ ucfirst(($statuses[$record->id] ?? \App\Domain\Enums\ObligationStatus::Open)->value) }}</div>
                        @endforelse
                    </div>
                </a>
            @empty
                <div class="px-5 py-14 text-center">
                    <span class="app-icon-badge mx-auto size-11"><flux:icon name="check" class="size-5" /></span>
                    <flux:heading size="lg" class="mt-4">No promises yet</flux:heading>
                    <flux:text class="mt-2">A promise records who, the direction, and the amount.</flux:text>
                    <flux:text class="mt-1">Settle the promise when it is done.</flux:text>
                    <a href="{{ route('promises.create', $profile) }}" wire:navigate class="mt-5 inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">Add a promise <span aria-hidden="true">→</span></a>
                </div>
            @endforelse
        </div>
    </section>
</div>
