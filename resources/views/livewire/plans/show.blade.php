<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('plans.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('plans.back') }}</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ __('plans.repayment_plan') }}</flux:heading>
        <flux:text class="mt-2">{{ $period->starts_on->format('d M Y') }} – {{ $period->ends_on->format('d M Y') }} · {{ $period->currency }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <div class="app-metric app-metric-primary rounded-2xl p-5"><flux:text>{{ __('plans.income') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::display($period->income_minor, $period->currency) }}</div></div>
        <div class="app-metric rounded-2xl p-5"><flux:text>{{ __('plans.essentials') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::display($period->essential_minor, $period->currency) }}</div></div>
        <div class="app-metric rounded-2xl p-5"><flux:text>{{ __('plans.reserve') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::display($period->reserve_minor, $period->currency) }}</div></div>
        <div class="app-metric app-metric-secondary rounded-2xl p-5"><flux:text>{{ __('plans.capacity') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::display($period->capacity(), $period->currency) }}</div></div>
    </div>

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
            <div><flux:heading size="lg">{{ __('plans.suggested_allocations') }}</flux:heading><flux:text class="mt-1">{{ __('plans.advice_only') }}</flux:text></div>
            @can('createObligation', $profile)
                <div class="flex flex-wrap items-end gap-3">
                    <flux:select wire:model.live="strategy" :label="__('plans.strategy')">
                        @foreach ($strategies as $planStrategy)
                            <flux:select.option value="{{ $planStrategy->value }}">{{ __('plans.strategies.'.$planStrategy->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:button wire:click="generate" variant="primary" wire:loading.attr="disabled">{{ __('plans.generate') }}</flux:button>
                </div>
            @endcan
        </div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($plan?->allocations ?? [] as $allocation)
                @php($line = $progress[$allocation->id] ?? ['planned_minor' => $allocation->planned_minor, 'paid_minor' => 0])
                <div class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><div class="font-medium">{{ $allocation->obligation->title }}</div><div class="text-sm text-zinc-500">{{ __('plans.planned') }} {{ \App\Domain\Money\Money::display($line['planned_minor'], $period->currency) }}</div></div>
                    <div class="text-sm font-semibold text-emerald-700">{{ __('plans.paid') }} {{ \App\Domain\Money\Money::display($line['paid_minor'], $period->currency) }}</div>
                </div>
            @empty
                <div class="px-5 py-10 text-sm text-zinc-500">{{ __('plans.generate_prompt') }}</div>
            @endforelse
        </div>
    </section>

    <section class="app-card rounded-2xl p-5 sm:p-7">
        <div>
            <flux:heading size="lg">{{ __('plans.debt_free_projection') }}</flux:heading>
            <flux:text class="mt-1">{{ __('plans.projection_intro') }}</flux:text>
        </div>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div class="app-metric rounded-xl p-4"><flux:text>{{ __('plans.estimated_time') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ $projection['months_to_free'] }} {{ __('plans.months') }}</div></div>
            <div class="app-metric rounded-xl p-4"><flux:text>{{ __('plans.estimated_charges') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ \App\Domain\Money\Money::display($projection['total_estimated_charges_minor'], $period->currency) }}</div></div>
            <div class="app-metric rounded-xl p-4"><flux:text>{{ __('plans.with_extra') }}</flux:text><div class="mt-2 text-xl font-semibold">{{ $projection['extra_months_to_free'] }} {{ __('plans.months') }}</div><div class="mt-1 text-xs text-zinc-500">{{ __('plans.months_earlier', ['months' => $projection['months_earlier'], 'amount' => \App\Domain\Money\Money::display($projection['charges_saved_minor'], $period->currency)]) }}</div></div>
        </div>
        @if ($projection['collect_first'] !== [])
            <div class="mt-5 rounded-xl bg-cyan-50 p-4 text-sm text-cyan-950 dark:bg-cyan-950/30 dark:text-cyan-100">
                <div class="font-semibold">{{ __('plans.collect_first') }}</div>
                <div class="mt-1">{{ __('plans.collect_first_intro') }}</div>
                <ul class="mt-2 space-y-1">
                    @foreach ($projection['collect_first'] as $receivable)
                        <li>{{ $receivable['title'] }}{{ $receivable['party'] !== '' ? ' · '.$receivable['party'] : '' }} — {{ \App\Domain\Money\Money::display($receivable['amount_minor'], $receivable['currency']) }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
</div>
