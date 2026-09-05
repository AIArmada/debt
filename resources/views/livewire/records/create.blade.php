<div class="mx-auto w-full max-w-4xl">
    <div class="mb-7 flex items-start gap-4">
        <span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="document-plus" class="size-6" /></span>
        <div>
            <div class="app-eyebrow">New arrangement</div>
            <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Create a record</flux:heading>
            <flux:text class="mt-2 max-w-2xl leading-6">Start one case or arrangement, then keep every obligation inside it clear and independently trackable.</flux:text>
        </div>
    </div>
    <div class="mb-5 flex items-start gap-3 rounded-xl border border-sky-100 bg-sky-50/70 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/25 dark:text-sky-100">
        <flux:icon name="light-bulb" class="mt-0.5 size-5 shrink-0 text-sky-700 dark:text-sky-300" />
        <p><span class="font-semibold">A useful starting point:</span> name the arrangement broadly, then add the exact money, item, service, or action you want to track first. You can add more later.</p>
    </div>
    <form wire:submit="submit" novalidate class="app-card space-y-7 rounded-2xl p-6 sm:p-7">
        <section class="space-y-5">
            <div class="flex items-start gap-3"><span class="app-step">01</span><div><flux:heading size="lg">The arrangement</flux:heading><flux:text class="mt-1 text-sm leading-5">This shared context is used for the people, general notes, and evidence that apply to the whole record.</flux:text></div></div>
            <div class="grid gap-5 sm:grid-cols-2"><flux:select wire:model="profileId" label="Profile">@foreach ($profiles as $profile)<flux:select.option :value="$profile->id">{{ $profile->name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="partyMode" label="Who is involved?"><flux:select.option value="none">I’ll add a party later</flux:select.option><flux:select.option value="existing">Choose an existing party</flux:select.option><flux:select.option value="new">Create a new party</flux:select.option></flux:select></div>
            @if ($partyMode === 'existing')
                <flux:select wire:model="primaryPartyId" label="Party" placeholder="Choose a person, organisation, group, or estate"><flux:select.option value="">Choose a party</flux:select.option>@foreach ($parties as $party)<flux:select.option :value="$party->id">{{ $party->preferred_name }} · {{ $party->kindLabel() }}</flux:select.option>@endforeach</flux:select>
                <flux:text class="text-sm">Party details live in the Parties directory. You can add contacts, addresses, relationships, and payment destinations there.</flux:text>
            @elseif ($partyMode === 'new')
                <div class="grid gap-5 sm:grid-cols-2"><flux:input wire:model="newPartyName" label="Party name" placeholder="e.g. Aminah, Maybank, or Family household" required /><flux:select wire:model="newPartyKind" label="Party type"><flux:select.option value="individual">Person</flux:select.option><flux:select.option value="organization">Organisation</flux:select.option><flux:select.option value="group">Group or household</flux:select.option><flux:select.option value="estate_or_trust">Estate or trust</flux:select.option><flux:select.option value="unidentified">Unidentified for now</flux:select.option></flux:select></div>
                <flux:text class="text-sm">You can complete their contact and payment details after saving this record.</flux:text>
            @endif
            <flux:input wire:model="recordTitle" label="Record title" placeholder="e.g. Ahmad family arrangement" required /><flux:textarea wire:model="recordDescription" label="Shared context" placeholder="What is this arrangement about? Add the general background once here." rows="4" /><flux:select wire:model="sensitivity" label="Visibility"><flux:select.option value="private">Private to people with profile access</flux:select.option><flux:select.option value="shared">Shared with all profile collaborators</flux:select.option></flux:select>
        </section>
        <section class="space-y-5 border-t border-zinc-200 pt-7 dark:border-zinc-700"><div class="flex items-start gap-3"><span class="app-step">02</span><div><flux:heading size="lg">First obligation</flux:heading><flux:text class="mt-1 text-sm leading-5">Start with what you know now. You can add more obligations—money, assets, services, and actions—after the record is created.</flux:text></div></div>@include('livewire.records.obligation-fields')</section>
        @if ($showReview)
            <section class="space-y-4 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 dark:border-emerald-900/70 dark:bg-emerald-950/20">
                <div><flux:heading size="lg">Review before saving</flux:heading><flux:text class="mt-1 text-sm">Check the direction and the starting facts. This is what will be saved as the first obligation.</flux:text></div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-white/80 p-4 dark:bg-zinc-900/70"><div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Arrangement</div><div class="mt-2 font-semibold">{{ $recordTitle }}</div><div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $partyMode === 'new' ? ($newPartyName ?: 'New party') : ($partyMode === 'existing' ? ($parties->firstWhere('id', $primaryPartyId)?->preferred_name ?? 'Selected party') : 'No party recorded yet') }} · {{ $sensitivity === 'shared' ? 'Shared with profile collaborators' : 'Private to profile access' }}</div></div>
                    <div class="rounded-xl bg-white/80 p-4 dark:bg-zinc-900/70"><div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Context</div><div class="mt-2 line-clamp-4 whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $recordDescription ?: 'No shared context added.' }}</div></div>
                </div>
                <div class="divide-y divide-emerald-200/80 rounded-xl bg-white/80 dark:divide-emerald-900/50 dark:bg-zinc-900/70">
                    @foreach ($this->reviewSummary() as $item)
                        <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4"><span class="text-sm text-zinc-500">{{ $item['label'] }}</span><span class="text-right text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $item['value'] }}</span></div>
                    @endforeach
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-100/60 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-100">A planned movement, exchange rate, or later evidence can be added after this record exists. The starting balance is not silently converted.</div>
            </section>
        @endif
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><flux:button :href="route('records.index')" wire:navigate variant="ghost">Cancel</flux:button>@if ($showReview)<flux:button type="button" wire:click="$set('showReview', false)" variant="ghost">Edit details</flux:button><flux:button type="submit" variant="primary">Save record</flux:button>@else<flux:button type="submit" variant="primary">Review record</flux:button>@endif</div>
    </form>
</div>
