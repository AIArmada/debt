<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <div class="app-eyebrow">{{ $profile->name }}</div>
            <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ __('promises.title') }}</flux:heading>
            <flux:text class="mt-2 leading-6">{{ __('promises.intro') }}</flux:text>
        </div>
        <a href="{{ route('promises.create', $profile) }}" wire:navigate class="app-primary-link inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold">
            <flux:icon name="plus" class="size-4" />
            {{ __('promises.new') }}
        </a>
    </div>

    <div class="flex flex-wrap gap-3 text-sm">
        @foreach (\App\Domain\Enums\RecordArchiveFilter::cases() as $filter)
            <button type="button" wire:click="$set('statusFilter', '{{ $filter->value }}')" wire:loading.attr="disabled" class="{{ $statusFilter === $filter->value ? 'font-semibold text-emerald-700' : 'text-zinc-500' }}">{{ __('promises.filters.'.$filter->value) }} <span class="text-xs">({{ $filterCounts[$filter->value] ?? 0 }})</span></button>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-metric app-metric-primary rounded-2xl p-5">
            <flux:text>{{ __('promises.to_pay') }}</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold tracking-tight">
                @forelse ($totals['to_pay'] as $currency => $amount)
                    <div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>
                @empty
                    <div>—</div>
                @endforelse
            </div>
            <flux:text class="mt-2 text-sm">{{ __('promises.grouped_by_currency') }}</flux:text>
        </div>
        <div class="app-metric app-metric-secondary rounded-2xl p-5">
            <flux:text>{{ __('promises.to_receive') }}</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold tracking-tight text-cyan-800">
                @forelse ($totals['to_receive'] as $currency => $amount)
                    <div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>
                @empty
                    <div>—</div>
                @endforelse
            </div>
            <flux:text class="mt-2 text-sm">{{ __('promises.grouped_by_currency') }}</flux:text>
        </div>
    </div>

    <a href="{{ route('help.debt-support') }}" wire:navigate class="-mt-3 text-sm font-medium text-emerald-700 hover:underline">{{ __('promises.debt_support_link') }}</a>

    <div class="relative">
        <flux:input wire:model.live.debounce.250ms="search" icon="magnifying-glass" :placeholder="__('promises.search')" :aria-label="__('promises.search_label')" />
    </div>

    @if ($dueSoon->isNotEmpty())
        <section class="app-card rounded-2xl p-5">
            <flux:heading size="lg">{{ __('promises.due_soon') }}</flux:heading>
            <div class="mt-4 space-y-2">
                @foreach ($dueSoon as $dueRecord)
                    @foreach ($dueRecord->obligations as $dueObligation)
                        <a href="{{ route('promises.show', [$profile, $dueRecord]) }}" wire:navigate class="flex items-center justify-between gap-4 rounded-xl bg-amber-50 px-3 py-3 text-sm text-amber-950">
                            <span>{{ $dueRecord->title }}</span>
                            @php($nextInstalment = $dueObligation->instalments->first(fn ($instalment) => $instalment->paid_minor < $instalment->amount_minor))
                            @php($dueDate = $nextInstalment?->due_on ?? $dueObligation->due_on)
                            <span class="shrink-0">{{ $dueDate?->format('d M Y') ?? __('promises.reminder') }}@if ($dueDate && $profile->show_hijri && ($hijriDatePresenter->format($dueDate, true))) · {{ $hijriDatePresenter->format($dueDate, true) }} {{ __('promises.hijri_suffix') }}@endif</span>
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
                        <div class="font-medium text-zinc-950 dark:text-zinc-100">{{ $record->title }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-zinc-500">
                            @if ($party && $party->display_name !== $record->title)
                                <span>{{ $party->displayName() }}</span>
                                <span>·</span>
                            @endif
                            <span>{{ $obligation ? __('promises.directions.'.$obligation->direction->value) : __('promises.promise') }}</span>
                            <span>· {{ $record->obligations->count() }} {{ $record->obligations->count() === 1 ? __('promises.part') : __('promises.parts') }}</span>
                            @if ($overdue[$record->id] ?? false)
                                <x-status-badge tone="danger" :label="__('promises.overdue')" />
                            @endif
                            @if ($obligation?->due_on)
                                <span>· {{ __('promises.due', ['date' => $obligation->due_on->format('d M Y')]) }}@if ($profile->show_hijri && ($hijriDatePresenter->format($obligation->due_on, true))) · {{ $hijriDatePresenter->format($obligation->due_on, true) }} {{ __('promises.hijri_suffix') }}@endif</span>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-left sm:text-right">
                        <x-status-badge :tone="match ($statuses[$record->id] ?? \App\Domain\Enums\ObligationStatus::Open) { \App\Domain\Enums\ObligationStatus::Settled => 'success', \App\Domain\Enums\ObligationStatus::Disputed => 'danger', \App\Domain\Enums\ObligationStatus::Paused => 'warning', default => 'info' }" :label="__('promises.statuses.'.($statuses[$record->id] ?? \App\Domain\Enums\ObligationStatus::Open)->value)" />
                        @forelse ($balances[$record->id] ?? [] as $position)
                            <div class="font-semibold">{{ \App\Domain\Money\Money::display($position['amount'], $position['currency']) }}</div>
                            <div class="text-xs text-zinc-500">{{ __('promises.directions.'.$position['direction']->value) }} · {{ $position['currency'] }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ __('promises.breakdown.borrowed') }} {{ \App\Domain\Money\Money::display($position['borrowed_minor'], $position['currency']) }}
                                · {{ __('promises.breakdown.charges') }} {{ \App\Domain\Money\Money::display($position['charges_minor'], $position['currency']) }}
                                · {{ __('promises.breakdown.paid') }} {{ \App\Domain\Money\Money::display($position['paid_minor'], $position['currency']) }}
                            </div>
                        @empty
                        @endforelse
                    </div>
                </a>
            @empty
                <div class="px-5 py-14 text-center">
                    <span class="app-icon-badge mx-auto size-11"><flux:icon name="check" class="size-5" /></span>
                    <flux:heading size="lg" class="mt-4">{{ __('promises.no_promises') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('promises.no_promises_description') }}</flux:text>
                    <flux:text class="mt-1">{{ __('promises.settle_when_done') }}</flux:text>
                    <a href="{{ route('promises.create', $profile) }}" wire:navigate class="mt-5 inline-flex text-sm font-semibold text-emerald-700 hover:text-emerald-900">{{ __('promises.add_promise') }} <span aria-hidden="true">→</span></a>
                </div>
            @endforelse
        </div>
    </section>
</div>
