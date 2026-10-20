<x-layouts::auth :title="__('share.title')">
    <main class="mx-auto flex w-full max-w-2xl flex-col gap-5 px-4 py-8">
        <div class="app-card rounded-2xl p-6 sm:p-8">
            <div class="app-eyebrow">{{ __('share.shared_promise') }}</div>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $record['title'] }}</h1>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($record['obligations'] as $obligation)
                    <span class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">{{ $obligation['direction'] }} · {{ $obligation['status'] }}</span>
                @endforeach
            </div>

            @if (session('share-message'))
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('share-message') }}</div>
            @endif

            @if ($share['disputed_at'])
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('share.disputed_by', ['name' => $share['disputed_name']]) }}</div>
            @elseif ($share['acknowledged_at'] && (! $has_terms || $all_terms_accepted))
                <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ __('share.acknowledged_by', ['name' => $share['acknowledged_name']]) }}</div>
            @endif
        </div>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.balance') }}</h2>
            <div class="mt-4 space-y-2">
                @forelse ($balances as $balance)
                    <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span>{{ $balance['obligation_title'] }} · {{ $balance['direction'] }}</span>
                        <span class="font-semibold">{{ \App\Domain\Money\Money::display($balance['amount_minor'], $balance['currency']) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('share.no_balance') }}</p>
                @endforelse
            </div>
        </section>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.balance_breakdown') }}</h2>
            <div class="mt-4 space-y-3">
                @forelse ($breakdowns as $breakdown)
                    <div class="rounded-xl bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                        <div class="font-medium">{{ $breakdown['obligation_title'] }} · {{ $breakdown['currency'] }}</div>
                        <div class="mt-1 text-zinc-600 dark:text-zinc-300">
                            {{ __('share.borrowed') }} {{ \App\Domain\Money\Money::display($breakdown['borrowed_minor'], $breakdown['currency']) }}
                            · {{ __('share.charges') }} {{ \App\Domain\Money\Money::display($breakdown['charges_minor'], $breakdown['currency']) }}
                            · {{ __('share.paid') }} {{ \App\Domain\Money\Money::display($breakdown['paid_minor'], $breakdown['currency']) }}
                        </div>
                        <div class="mt-1 text-zinc-500 dark:text-zinc-400">
                            {{ __('share.unpaid_principal') }} {{ \App\Domain\Money\Money::display($breakdown['unpaid_principal_minor'], $breakdown['currency']) }}
                            · {{ __('share.unpaid_charges') }} {{ \App\Domain\Money\Money::display($breakdown['unpaid_charges_minor'], $breakdown['currency']) }}
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('share.no_breakdown') }}</p>
                @endforelse
            </div>
        </section>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.instalments') }}</h2>
            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($instalments as $instalment)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <span>{{ __('share.instalment_sequence', ['sequence' => $instalment['sequence']]) }} · {{ $instalment['due_on'] }} · {{ __('share.instalment_statuses.'.$instalment['status']) }}</span>
                        <span class="font-semibold">{{ \App\Domain\Money\Money::display($instalment['paid_minor'], $instalment['currency']) }} / {{ \App\Domain\Money\Money::display($instalment['amount_minor'], $instalment['currency']) }}</span>
                    </div>
                @empty
                    <p class="py-3 text-sm text-zinc-500">{{ __('share.no_instalments') }}</p>
                @endforelse
            </div>
        </section>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.terms') }}</h2>
            <div class="mt-4 space-y-3">
                @forelse ($terms as $term)
                    <div class="rounded-xl bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-900">
                        <div class="font-medium">{{ $term['obligation_title'] }}</div>
                        <div class="mt-1 text-zinc-600 dark:text-zinc-300">
                            {{ __('share.charge_modes.'.$term['mode']) }} · {{ __('share.charge_kinds.'.$term['kind']) }}@if ($term['basis']) · {{ __('share.charge_bases.'.$term['basis']) }}@endif
                            @if ($term['rate']) · {{ $term['rate'] }} per {{ __('share.charge_periods.'.$term['period']) }}@endif
                        </div>
                        <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.grace_period', ['days' => $term['grace_days'], 'allocation' => __('share.allocation_orders.'.$term['allocation_order'])]) }}</div>
                        @if ($term['cap_minor'] !== null)
                            <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.lifetime_cap', ['amount' => \App\Domain\Money\Money::display($term['cap_minor'], $term['currency'])]) }}</div>
                        @endif
                        @if ($term['total_payable_minor'] !== null)
                            <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.total_payable', ['amount' => \App\Domain\Money\Money::display($term['total_payable_minor'], $term['currency'])]) }}</div>
                        @endif
                        @if ($term['penalty_kind'] !== 'none')
                            @if ($term['penalty_kind'] === 'fixed')
                                <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.fixed_penalty', ['amount' => \App\Domain\Money\Money::display($term['penalty_value'], $term['currency'])]) }}</div>
                            @elseif ($term['penalty_kind'] === 'percent_of_overdue')
                                <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.percentage_penalty', ['percent' => number_format($term['penalty_value'] / 100, 2)]) }}</div>
                            @endif
                        @endif
                        @if ($term['auto_apply_on_acceptance'])
                            <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.automatic_after_acceptance') }}</div>
                        @endif
                        @if ($term['is_formal'])
                            <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.formal_debt') }}</div>
                        @endif
                        @if ($term['fixed_total_agreed_upfront_at'])
                            <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ __('share.fixed_agreed_date', ['date' => $term['fixed_total_agreed_upfront_at']->format('d M Y')]) }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('share.no_terms') }}</p>
                @endforelse
            </div>
        </section>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.movements') }}</h2>
            <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($movements as $movement)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <span>{{ $movement['title'] }} · {{ __('share.money_entries.'.$movement['entry']) }} · {{ $movement['occurred_on'] }}</span>
                        <span class="font-semibold">{{ \App\Domain\Money\Money::display($movement['amount_minor'], $movement['currency']) }}</span>
                    </div>
                @empty
                    <p class="py-3 text-sm text-zinc-500">{{ __('share.no_movements') }}</p>
                @endforelse
                @foreach ($returns as $return)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <span>{{ $return['title'] }} · {{ __('share.returned', ['date' => $return['occurred_on']]) }}</span>
                        <span class="font-semibold">{{ $return['quantity'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.evidence') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                @forelse ($evidence_names as $name)
                    <li>{{ $name }}</li>
                @empty
                    <li>{{ __('share.no_evidence') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="app-card rounded-2xl p-6 sm:p-8">
            <h2 class="text-lg font-semibold">{{ __('share.guarantors') }}</h2>
            <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                @forelse ($guarantors as $guarantor)
                    <li>{{ $guarantor }}</li>
                @empty
                    <li>{{ __('share.no_guarantors') }}</li>
                @endforelse
            </ul>
        </section>

        @if ($show_acknowledge_form)
            <section class="app-card rounded-2xl p-6 sm:p-8">
                <form method="POST" action="{{ route('shares.acknowledge', ['token' => request()->route('token'), 'lang' => app()->getLocale()]) }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm font-medium">{{ __('share.your_name') }}
                        <input name="name" required maxlength="255" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                    </label>
                    @foreach ($terms_hashes as $obligationId => $hash)
                        <input type="hidden" name="termsHashes[{{ $obligationId }}]" value="{{ $hash }}" />
                    @endforeach
                    <button type="submit" class="app-primary-link rounded-lg px-4 py-2.5 text-sm font-semibold">{{ $has_terms ? __('share.accept_terms') : __('share.acknowledge') }}</button>
                    @error('termsHashes')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                </form>
            </section>
        @endif

        @if ($show_dispute_form)
            <section class="app-card rounded-2xl p-6 sm:p-8">
                <form method="POST" action="{{ route('shares.dispute', ['token' => request()->route('token'), 'lang' => app()->getLocale()]) }}" class="space-y-4">
                    @csrf
                    <label class="block text-sm font-medium">{{ __('share.your_name') }}
                        <input name="name" required maxlength="255" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900" />
                    </label>
                    <label class="block text-sm font-medium">{{ __('share.note_optional') }}
                        <textarea name="note" maxlength="2000" rows="3" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm dark:border-zinc-700 dark:bg-zinc-900"></textarea>
                    </label>
                    <button type="submit" class="rounded-lg border border-amber-300 px-4 py-2.5 text-sm font-semibold text-amber-900">{{ __('share.dispute') }}</button>
                </form>
            </section>
        @endif
    </main>
</x-layouts::auth>
