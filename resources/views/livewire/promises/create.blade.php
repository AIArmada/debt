<div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('promises.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('promises.back_to_promises') }}</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ __('promises.create_title') }}</flux:heading>
        <flux:text class="mt-2 leading-6">{{ __('promises.create_intro') }}</flux:text>
    </div>

    <form wire:submit="save" x-data="dirtyForm({ partyName: $wire.entangle('partyName'), purpose: $wire.entangle('purpose'), currency: $wire.entangle('currency'), subjectType: $wire.entangle('subjectType'), direction: $wire.entangle('direction'), amount: $wire.entangle('amount'), dueOn: $wire.entangle('dueOn'), note: $wire.entangle('note'), visibility: $wire.entangle('visibility'), quantityName: $wire.entangle('quantityName'), quantityTotal: $wire.entangle('quantityTotal'), quantityUnit: $wire.entangle('quantityUnit'), doneCriteria: $wire.entangle('doneCriteria') })" class="app-card space-y-6 rounded-2xl p-5 sm:p-7">
        <flux:input wire:model="purpose" :label="__('promises.purpose_label')" :placeholder="__('promises.purpose_placeholder')" :description="__('promises.purpose_description')" />
        <div class="relative">
            <flux:input wire:model.live.debounce.250ms="partyName" :label="__('promises.who')" :placeholder="__('promises.person_placeholder')" :description="__('promises.choose_or_new_person')" required autofocus />
            @if ($partyMatches->isNotEmpty())
                <div class="absolute inset-x-0 top-full z-10 mt-1 overflow-hidden rounded-xl border border-zinc-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                    @foreach ($partyMatches as $partyMatch)
                        <button type="button" wire:click="selectParty('{{ $partyMatch->id }}')" wire:key="party-{{ $partyMatch->id }}" wire:loading.attr="disabled" class="flex min-h-11 w-full items-center justify-between rounded-lg px-3 py-2 text-start text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $partyMatch->displayName() }}</span>
                            <span class="text-xs text-zinc-500">{{ __('promises.use_existing') }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
            @if ($partyId)
                <p class="mt-2 text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ __('promises.using_existing_person') }}</p>
            @endif
        </div>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-zinc-900 dark:text-white">{{ __('promises.kind_of_promise') }}</legend>
            <div class="grid gap-3 sm:grid-cols-3">
                <button type="button" wire:click="selectSubjectType('{{ \App\Domain\Enums\SubjectType::Money->value }}')" wire:loading.attr="disabled" class="{{ $subjectType === \App\Domain\Enums\SubjectType::Money ? 'border-emerald-600 bg-emerald-50 text-emerald-950 ring-2 ring-emerald-600/20 dark:border-emerald-400 dark:bg-emerald-950/30 dark:text-emerald-100' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' }} min-h-14 rounded-xl border px-4 py-3 text-start text-sm font-semibold transition">{{ __('promises.subjects.money') }}</button>
                <button type="button" wire:click="selectSubjectType('{{ \App\Domain\Enums\SubjectType::Quantity->value }}')" wire:loading.attr="disabled" class="{{ $subjectType === \App\Domain\Enums\SubjectType::Quantity ? 'border-cyan-600 bg-cyan-50 text-cyan-950 ring-2 ring-cyan-600/20 dark:border-cyan-400 dark:bg-cyan-950/30 dark:text-cyan-100' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' }} min-h-14 rounded-xl border px-4 py-3 text-start text-sm font-semibold transition">{{ __('promises.subjects.quantity') }}</button>
                <button type="button" wire:click="selectSubjectType('{{ \App\Domain\Enums\SubjectType::Commitment->value }}')" wire:loading.attr="disabled" class="{{ $subjectType === \App\Domain\Enums\SubjectType::Commitment ? 'border-amber-600 bg-amber-50 text-amber-950 ring-2 ring-amber-600/20 dark:border-amber-400 dark:bg-amber-950/30 dark:text-amber-100' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' }} min-h-14 rounded-xl border px-4 py-3 text-start text-sm font-semibold transition">{{ __('promises.subjects.commitment') }}</button>
            </div>
        </fieldset>

        <fieldset>
            <legend class="mb-2 text-sm font-medium text-zinc-900 dark:text-white">{{ __('promises.direction') }}</legend>
            <div class="grid gap-3 sm:grid-cols-2">
                <button type="button" wire:click="selectDirection('{{ \App\Domain\Enums\Direction::Payable->value }}')" wire:loading.attr="disabled" class="{{ $direction === \App\Domain\Enums\Direction::Payable->value ? 'border-emerald-600 bg-emerald-50 text-emerald-950 ring-2 ring-emerald-600/20 dark:border-emerald-400 dark:bg-emerald-950/30 dark:text-emerald-100' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' }} min-h-16 rounded-xl border px-4 py-3 text-start text-sm font-semibold transition">
                    <span class="block">{{ __('promises.directions.payable') }}</span>
                    <span class="mt-1 block text-xs font-normal opacity-70">{{ __('promises.direction_descriptions.payable.'.$subjectType->value) }}</span>
                </button>
                <button type="button" wire:click="selectDirection('{{ \App\Domain\Enums\Direction::Receivable->value }}')" wire:loading.attr="disabled" class="{{ $direction === \App\Domain\Enums\Direction::Receivable->value ? 'border-cyan-600 bg-cyan-50 text-cyan-950 ring-2 ring-cyan-600/20 dark:border-cyan-400 dark:bg-cyan-950/30 dark:text-cyan-100' : 'border-zinc-200 bg-white text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200' }} min-h-16 rounded-xl border px-4 py-3 text-start text-sm font-semibold transition">
                    <span class="block">{{ __('promises.directions.receivable') }}</span>
                    <span class="mt-1 block text-xs font-normal opacity-70">{{ __('promises.direction_descriptions.receivable.'.$subjectType->value) }}</span>
                </button>
            </div>
        </fieldset>

        @if ($subjectType === \App\Domain\Enums\SubjectType::Money)
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="amount" type="text" inputmode="decimal" :label="__('promises.how_much')" placeholder="0.00" :description="__('promises.amount_in', ['currency' => $currency])" required />
                <flux:select wire:model="currency" :label="__('promises.currency')" required>
                    @foreach (\App\Domain\Money\Currency::codes() as $currencyCode)
                        <flux:select.option value="{{ $currencyCode }}">{{ $currencyCode }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @elseif ($subjectType === \App\Domain\Enums\SubjectType::Quantity)
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="quantityName" :label="__('promises.what_is_it')" :placeholder="__('promises.quantity_name_placeholder')" required />
                <flux:input wire:model="quantityTotal" type="text" inputmode="decimal" :label="__('promises.how_many')" placeholder="1" required />
                <flux:input wire:model="quantityUnit" :label="__('promises.unit')" :placeholder="__('promises.unit_placeholder')" required />
                <label class="flex items-center gap-3 self-end pb-2 text-sm text-zinc-700 dark:text-zinc-200"><input wire:model="isFractionable" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> {{ __('promises.fractions_allowed') }}</label>
            </div>
        @else
            <flux:textarea wire:model="doneCriteria" :label="__('promises.completion_meaning')" :placeholder="__('promises.completion_placeholder')" rows="3" required />
        @endif
        <flux:input wire:model="dueOn" type="date" :label="__('promises.due_date')" :description="__('promises.optional_due_date')" />
        <flux:textarea wire:model="note" :label="__('promises.note')" :placeholder="__('promises.optional_context')" rows="4" />

        <flux:select wire:model="visibility" :label="__('promises.visibility_question')" required>
            <option value="{{ \App\Domain\Enums\RecordVisibility::Shared->value }}">{{ __('promises.shared_visibility') }}</option>
            <option value="{{ \App\Domain\Enums\RecordVisibility::Private->value }}">{{ __('promises.private_visibility') }}</option>
        </flux:select>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <flux:button :href="route('promises.index', $profile)" wire:navigate variant="ghost">{{ __('promises.cancel') }}</flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="w-full sm:w-auto">{{ __('promises.save_promise') }}</flux:button>
        </div>
    </form>
</div>
