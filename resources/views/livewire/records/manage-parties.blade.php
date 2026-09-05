<section class="app-card rounded-2xl p-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex items-center gap-2"><span class="app-icon-badge size-9"><flux:icon name="user-group" class="size-4" /></span><flux:heading size="lg">Participants</flux:heading></div>
            <flux:text class="mt-2 text-sm">One record may involve several parties. Add their role here; add their specific share or obligation role inside the relevant obligation.</flux:text>
        </div>
        @can('update', $record)
            <div class="text-xs text-zinc-500">Private contact details remain restricted.</div>
        @endcan
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($record->partyLinks as $link)
            <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="flex items-start justify-between gap-3">
                    <div><div class="font-medium">{{ $link->party->preferred_name }}</div><div class="mt-1 text-xs text-zinc-500">{{ str_replace('_', ' ', ucfirst($link->role)) }}@if ($link->is_primary) · Primary @endif</div></div>
                    @can('update', $record)<flux:button size="sm" variant="ghost" wire:click="remove('{{ $link->id }}')" wire:confirm="Remove this participant from the record?">Remove</flux:button>@endcan
                </div>
                @if ($link->notes)<p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $link->notes }}</p>@endif
                @if ($link->party->contacts->isNotEmpty() && $record->sensitivity === 'shared')
                    <div class="mt-3 text-xs text-zinc-500">{{ $link->party->contacts->where('is_message_safe', true)->first()?->value }}</div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 sm:col-span-2 lg:col-span-3">No participants recorded yet. You can still create an obligation first and attach parties later.</div>
        @endforelse
    </div>

    @can('update', $record)
        @if ($parties->isNotEmpty())
            <form wire:submit="add" class="mt-5 grid gap-3 border-t border-zinc-100 pt-5 sm:grid-cols-[1fr_1fr_1fr_auto] dark:border-zinc-800">
                <flux:select wire:model="partyId" label="Party" placeholder="Choose a party">
                    <flux:select.option value="">Choose a party</flux:select.option>
                    @foreach ($parties as $party)
                        <flux:select.option :value="$party->id">{{ $party->preferred_name }} · {{ $party->kindLabel() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="role" label="Role">
                    <flux:select.option value="other_party">Other party</flux:select.option>
                    <flux:select.option value="beneficiary">Beneficiary</flux:select.option>
                    <flux:select.option value="obligor">Obligor</flux:select.option>
                    <flux:select.option value="guarantor">Guarantor</flux:select.option>
                    <flux:select.option value="witness">Witness</flux:select.option>
                    <flux:select.option value="representative">Representative</flux:select.option>
                    <flux:select.option value="contact">Contact person</flux:select.option>
                    <flux:select.option value="custodian">Custodian</flux:select.option>
                    <flux:select.option value="service_recipient">Service recipient</flux:select.option>
                </flux:select>
                <flux:input wire:model="notes" label="Note (optional)" placeholder="What is their role here?" />
                <flux:button type="submit" variant="outline" class="self-end"><flux:icon name="plus" class="size-4" /> Add</flux:button>
            </form>
        @else
            <div class="mt-5 border-t border-zinc-100 pt-5 text-sm text-zinc-500 dark:border-zinc-800">Add a party in the Parties directory before assigning participants.</div>
        @endif
    @endcan
</section>
