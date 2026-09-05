<div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-8">
        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_18rem] md:items-stretch">
            <div class="app-page-intro flex items-start gap-4 rounded-2xl border border-emerald-100/80 bg-white/45 p-5 shadow-sm dark:border-emerald-900/50 dark:bg-zinc-900/35 sm:p-6">
                <span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="wallet" class="size-6" /></span>
                <div>
                    <div class="app-eyebrow">Personal command centre</div>
                    <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Financial overview</flux:heading>
                    <flux:text class="mt-2 max-w-xl leading-6">A calm snapshot of what is owed, what is due, and what you have already recorded for {{ $profile->name }}.</flux:text>
                </div>
            </div>
            <div class="app-action-panel flex flex-col justify-between gap-5 rounded-2xl p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="app-eyebrow text-emerald-700 dark:text-emerald-300">Start here</div>
                        <div class="mt-1 text-lg font-semibold tracking-tight text-zinc-950 dark:text-white">Add a record</div>
                        <p class="mt-1 text-sm leading-5 text-zinc-600 dark:text-zinc-300">Capture a new arrangement, then add its details over time.</p>
                    </div>
                    <span class="app-action-icon"><flux:icon name="plus" class="size-5" /></span>
                </div>
                <div class="flex flex-col gap-2">
                    <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Viewing profile</span>
                    <flux:select wire:model.live="profileId" class="w-full" aria-label="Financial profile">
                    @foreach ($profiles as $item)
                        <flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>
                    @endforeach
                    </flux:select>
                    <a href="{{ route('records.create') }}" wire:navigate class="app-primary-link mt-1 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold">
                        <flux:icon name="plus" class="size-4" />
                        New record
                    </a>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="app-metric app-metric-primary rounded-2xl p-5">
                <div class="flex items-center gap-3"><span class="app-icon-badge size-9"><flux:icon name="arrow-up" class="size-4" /></span><flux:text>To pay</flux:text></div>
                <div class="mt-4 space-y-1 text-2xl font-semibold tracking-tight text-accent-content">@forelse ($payableByCurrency as $currency => $amount)<div>{{ \App\Domain\Money\MoneyAmount::format($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
                <flux:text class="mt-2 text-sm">Grouped by native currency · never combined</flux:text>
            </div>
            <div class="app-metric app-metric-secondary rounded-2xl p-5">
                <div class="flex items-center gap-3"><span class="app-icon-badge size-9 bg-cyan-100 text-cyan-700"><flux:icon name="arrow-down" class="size-4" /></span><flux:text>To receive</flux:text></div>
                <div class="mt-4 space-y-1 text-2xl font-semibold tracking-tight text-cyan-800">@forelse ($receivableByCurrency as $currency => $amount)<div>{{ \App\Domain\Money\MoneyAmount::format($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
                <flux:text class="mt-2 text-sm">Grouped by native currency · never combined</flux:text>
            </div>
            <div class="app-metric app-metric-neutral rounded-2xl p-5">
                <div class="flex items-center gap-3"><span class="app-icon-badge size-9 bg-zinc-100 text-zinc-600"><flux:icon name="calendar-days" class="size-4" /></span><flux:text>Minimum this month</flux:text></div>
                <div class="mt-4 space-y-1 text-2xl font-semibold tracking-tight text-zinc-900">@forelse ($monthlyMinimumByCurrency as $currency => $amount)<div>{{ \App\Domain\Money\MoneyAmount::format($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
                <flux:text class="mt-2 text-sm">Minimums grouped by native currency</flux:text>
            </div>
            <div class="app-metric app-metric-soft rounded-2xl p-5">
                <div class="flex items-center gap-3"><span class="app-icon-badge size-9 bg-[#e4f1e8] text-[#286653]"><flux:icon name="sparkles" class="size-4" /></span><flux:text>Other obligations</flux:text></div>
                <div class="mt-4 text-3xl font-semibold tracking-tight text-zinc-900">{{ $nonMonetaryCount }}</div>
                <flux:text class="mt-2 text-sm">Active assets, services & commitments</flux:text>
            </div>
        </div>
        <section class="app-card rounded-2xl px-5 py-4" aria-label="Status guide">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="app-eyebrow">At a glance</div>
                    <div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">The labels and colors use the same meaning everywhere in the app.</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-status-badge tone="info" label="Open / active" />
                    <x-status-badge tone="success" label="Settled / recorded" />
                    <x-status-badge tone="warning" label="Review / due soon" />
                    <x-status-badge tone="danger" label="Urgent / overdue" />
                    <x-direction-badge direction="payable" label="To pay" />
                    <x-direction-badge direction="receivable" label="To receive" />
                    <x-obligation-badge kind="money" label="Money" />
                    <x-obligation-badge kind="asset" label="Asset" />
                    <x-obligation-badge kind="service" label="Service" />
                    <x-obligation-badge kind="action" label="Promise / action" />
                </div>
            </div>
        </section>
        @if (count($unconvertedCurrencies) > 0)
            <div class="rounded-xl border border-amber-200/80 bg-amber-50/70 px-4 py-3 text-sm text-amber-900 shadow-sm">This profile has native balances in {{ implode(', ', array_keys($unconvertedCurrencies)) }}. They stay separate from {{ $profile->base_currency }} because amounts in different currencies must not be added without an explicit dated comparison.</div>
        @endif

        <section class="app-card overflow-hidden rounded-2xl">
            <div class="app-card-header flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="app-section-icon"><flux:icon name="chart-bar" class="size-5" /></span>
                    <div>
                        <flux:heading size="lg">Repayment this period</flux:heading>
                        <flux:text class="mt-1 text-sm">See the profile total, the current plan, and what has actually been paid without mixing currencies.</flux:text>
                    </div>
                </div>
                <a wire:navigate href="{{ route('plans.index', ['profile' => $profile->id]) }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-100">Open budget & plans <span aria-hidden="true">→</span></a>
            </div>
            @if ($budget)
                <div class="border-b border-zinc-200/80 bg-zinc-50/70 px-5 py-3 text-sm text-zinc-600 dark:border-zinc-700/80 dark:bg-zinc-800/30 dark:text-zinc-300">{{ $budget->starts_on->format('d M Y') }} – {{ $budget->ends_on->format('d M Y') }} · {{ $plan?->name ?? 'No repayment plan generated yet' }}</div>
            @endif
            @if ($plan?->needsReview())
                <div class="border-b border-amber-200 bg-amber-50/70 px-5 py-3 text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">This repayment plan needs review: {{ $plan->review_reason ?: 'a confirmed movement changed its assumptions.' }} <a wire:navigate href="{{ route('plans.index', ['profile' => $profile->id]) }}" class="font-semibold underline underline-offset-2">Review plan</a></div>
            @endif
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($repaymentSummary as $summary)
                    <div class="grid gap-4 px-5 py-4 sm:grid-cols-[7rem_repeat(7,minmax(0,1fr))] sm:items-center">
                        <div><div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $summary['currency'] }}</div><div class="mt-1 text-xs text-zinc-500">Native totals</div></div>
                        <div><div class="text-xs text-zinc-500">To pay</div><div class="mt-1 font-medium">{{ App\Domain\Money\MoneyAmount::format($summary['payable'], $summary['currency']) }}</div></div>
                        <div><div class="text-xs text-zinc-500">To receive</div><div class="mt-1 font-medium">{{ App\Domain\Money\MoneyAmount::format($summary['receivable'], $summary['currency']) }}</div></div>
                        <div><div class="text-xs text-zinc-500">Minimum</div><div class="mt-1 font-medium">{{ App\Domain\Money\MoneyAmount::format($summary['minimum'], $summary['currency']) }}</div></div>
                        <div><div class="text-xs text-zinc-500">Planned</div><div class="mt-1 font-medium">{{ App\Domain\Money\MoneyAmount::format($summary['planned'], $summary['currency']) }}</div></div>
                        <div><div class="text-xs text-zinc-500">Paid toward plan</div><div class="mt-1 font-medium text-emerald-700 dark:text-emerald-300">{{ App\Domain\Money\MoneyAmount::format($summary['paid'], $summary['currency']) }}</div></div>
                        <div><div class="text-xs text-zinc-500">Actual paid this period</div><div class="mt-1 font-medium text-cyan-700 dark:text-cyan-300">{{ App\Domain\Money\MoneyAmount::format($summary['actual_paid'], $summary['currency']) }}</div></div>
                        <div><div class="text-xs text-zinc-500">Plan remaining</div><div class="mt-1 font-medium text-amber-700 dark:text-amber-300">{{ App\Domain\Money\MoneyAmount::format($summary['remaining'], $summary['currency']) }}</div></div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-sm text-zinc-500">Once this profile has an active money obligation, its native-currency total and plan progress will appear here.</div>
                @endforelse
            </div>
        </section>

        @if ($reversedObligations->isNotEmpty())
            <section class="rounded-2xl border border-amber-200/80 bg-amber-50/70 p-5 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><div class="app-eyebrow text-amber-700">Direction changed</div><flux:heading size="lg" class="mt-1 text-amber-950">Review reversed positions</flux:heading><flux:text class="mt-1 text-sm text-amber-900/80">These records started in one direction but their confirmed movements now show money coming back to you. Payment reminders are not appropriate until you review them.</flux:text></div><x-status-badge tone="warning" :label="$reversedObligations->count().' to review'" /></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach ($reversedObligations as $obligation)<a wire:navigate href="{{ route('records.show', $obligation->record) }}" class="rounded-xl border border-amber-200 bg-white/80 p-4 transition hover:bg-white dark:border-amber-900/60 dark:bg-zinc-900/60"><div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $obligation->title }}</div><div class="mt-1 text-sm text-amber-900/80">{{ $obligation->effectiveDirectionLabel() }} · {{ \App\Domain\Money\MoneyAmount::format($obligation->currentPositionAmount(), $obligation->currency) }}</div></a>@endforeach</div>
            </section>
        @endif

        @if ($pawnRisks->isNotEmpty())
            <section class="app-card overflow-hidden rounded-2xl border-amber-200/80 bg-amber-50/60">
                <div class="flex items-start justify-between gap-4 border-b border-amber-200/80 px-5 py-5"><div><div class="app-eyebrow text-amber-700">Needs a little attention</div><flux:heading size="lg" class="mt-1">Pawn & Ar-Rahnu watch</flux:heading><flux:text class="mt-1 text-sm">Maturity dates and redemption estimates that may need attention.</flux:text></div><x-status-badge tone="warning" :label="$pawnRisks->count().' active'" /></div>
                <div class="divide-y divide-amber-200/70">@foreach ($pawnRisks as $risk)<a wire:navigate href="{{ route('records.show', $risk['obligation']->record) }}" class="flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-amber-100/60"><div><div class="font-medium text-zinc-900">{{ $risk['obligation']->title }}</div><div class="mt-2"><x-risk-badge :days="$risk['days']" :label="$risk['label']" /></div><div class="mt-1 text-sm text-amber-900/70">{{ $risk['asset_count'] }} asset{{ $risk['asset_count'] === 1 ? '' : 's' }}</div></div><div class="text-right"><div class="font-semibold text-zinc-900">{{ $risk['redemption_total'] !== null && $risk['redemption_currency'] !== null ? \App\Domain\Money\MoneyAmount::format($risk['redemption_total'], $risk['redemption_currency']) : '—' }}</div><div class="mt-1 text-xs text-amber-900/70">{{ $risk['redemption_label'] }}</div></div></a>@endforeach</div>
            </section>
        @endif

        <section class="app-card overflow-hidden rounded-2xl">
            <div class="app-card-header flex items-center justify-between gap-4 px-5 py-5">
                <div class="flex items-start gap-3">
                    <span class="app-section-icon"><flux:icon name="calendar-days" class="size-5" /></span>
                    <div>
                    <flux:heading size="lg">Next actions</flux:heading>
                    <flux:text class="mt-1 text-sm">Upcoming obligations, presented in the order you need them.</flux:text>
                    </div>
                </div>
            </div>
            <div class="divide-y divide-zinc-200/80">
                @forelse ($upcoming as $obligation)
                    <a wire:navigate href="{{ route('records.show', $obligation->record) }}" class="flex items-center justify-between gap-4 px-5 py-5 transition hover:bg-zinc-50/80">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $obligation->title }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-2"><x-obligation-badge :kind="$obligation->obligation_kind" :label="$obligation->kindLabel()" /><x-direction-badge :direction="$obligation->obligation_kind === 'money' ? $obligation->currentPositionDirection() : $obligation->direction" :label="$obligation->obligation_kind === 'money' ? $obligation->effectiveDirectionLabel() : ($obligation->direction === 'payable' ? 'You owe / must do' : 'They owe / must do')" /><span class="text-sm text-zinc-500">{{ $obligation->record->primaryParty()?->preferred_name ?? 'No party recorded' }}</span></div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div class="font-medium">
                                @if ($obligation->obligation_kind === 'money')
                                    {{ \App\Domain\Money\MoneyAmount::format($obligation->currentPositionAmount(), $obligation->currency) }}
                                @elseif ($obligation->isQuantityBased())
                                    {{ \App\Domain\Money\Decimal::display($obligation->current_subject_quantity) }} {{ $obligation->subject_unit }} outstanding
                                @else
                                    Commitment outstanding
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-zinc-500">Due {{ $obligation->next_due_on->format('d M Y') }}</div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-14 text-center">
                        <span class="app-icon-badge mx-auto size-11"><flux:icon name="check" class="size-5" /></span>
                        <flux:heading size="lg">Nothing scheduled yet</flux:heading>
                        <flux:text class="mt-2">Add your first money, asset, service, or commitment obligation to begin building a clear picture.</flux:text>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
