<section id="contact-routes" class="app-card rounded-2xl p-6" x-data x-on:contact-route-editing.window="$nextTick(() => document.getElementById('contact-route-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">
    <div class="flex items-start gap-3"><span class="app-icon-badge"><flux:icon name="arrow-path" class="size-4" /></span><div><flux:heading size="lg">Contact routes</flux:heading><flux:text class="mt-1 text-sm">Record when someone should be reached through a personal assistant, relative, representative, or trusted contact. This does not change who the obligation belongs to.</flux:text></div></div>
    @if (session('contact-route-created'))<div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('contact-route-created') }}</div>@endif
    @if (session('contact-route-updated'))<div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('contact-route-updated') }}</div>@endif
    <form id="contact-route-form" wire:submit="save" class="mt-5 scroll-mt-6 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2 flex items-start justify-between gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 dark:border-emerald-900/50 dark:bg-emerald-950/20"><div><flux:heading size="sm">{{ $editingRouteId ? 'Edit contact route' : 'Add a contact route' }}</flux:heading><flux:text class="mt-1 text-xs">{{ $editingRouteId ? 'Update who to reach, the intermediary, or the instructions.' : 'Use this when the person is best reached through someone else.' }}</flux:text></div>@if ($editingRouteId)<flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit">Cancel edit</flux:button>@endif</div>
        <flux:select wire:model="partyId" label="Party to reach"><flux:select.option value="">Choose a party</flux:select.option>@foreach ($parties as $party)<flux:select.option value="{{ $party->id }}">{{ $party->preferred_name }}</flux:select.option>@endforeach</flux:select>
        <flux:select wire:model.live="viaPartyId" label="Reach them through"><flux:select.option value="">Choose an intermediary</flux:select.option>@foreach ($parties as $party)<flux:select.option value="{{ $party->id }}">{{ $party->preferred_name }}</flux:select.option>@endforeach</flux:select>
        @if ($viaPartyId)<flux:select wire:model="viaContactId" label="Intermediary contact point (optional)"><flux:select.option value="">Use the intermediary generally</flux:select.option>@foreach ($parties->firstWhere('id', $viaPartyId)?->contacts ?? [] as $contact)<flux:select.option value="{{ $contact->id }}">{{ ucfirst($contact->type) }} · {{ $contact->value }}</flux:select.option>@endforeach</flux:select>@endif
        <flux:select wire:model="relationshipType" label="Relationship"><flux:select.option value="personal_assistant">Personal assistant</flux:select.option><flux:select.option value="relative">Relative</flux:select.option><flux:select.option value="representative">Representative</flux:select.option><flux:select.option value="friend">Friend</flux:select.option><flux:select.option value="colleague">Colleague</flux:select.option><flux:select.option value="legal_contact">Legal contact</flux:select.option><flux:select.option value="emergency_contact">Emergency contact</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
        <flux:select wire:model="purpose" label="Purpose"><flux:select.option value="general">General</flux:select.option><flux:select.option value="payment">Payment</flux:select.option><flux:select.option value="delivery">Delivery</flux:select.option><flux:select.option value="legal">Legal</flux:select.option><flux:select.option value="emergency">Emergency</flux:select.option></flux:select>
        <flux:input wire:model="priority" type="number" min="1" max="99" label="Priority" /><flux:checkbox wire:model="isPrimary" label="Use as primary route for this purpose" />
        <div class="sm:col-span-2"><flux:textarea wire:model="instructions" label="Instructions (optional)" placeholder="e.g. Contact Farah first during working hours; do not disclose balance in messages." rows="2" /></div>
        <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="primary" class="min-w-max"><flux:icon name="{{ $editingRouteId ? 'pencil' : 'check' }}" class="app-button-icon size-4" /> {{ $editingRouteId ? 'Update contact route' : 'Save contact route' }}</flux:button></div>
    </form>
    @if ($routes->isNotEmpty())
        <div class="mt-6 space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Saved routes</div>
                <flux:text class="mt-1 text-xs">These routes are reusable guidance. View details before contacting someone, or edit them when circumstances change.</flux:text>
            </div>
            @foreach ($routes as $route)
                <div class="rounded-xl bg-zinc-50 px-3 py-3 text-sm dark:bg-zinc-800/60">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-medium">{{ $route->party->preferred_name }} via {{ $route->viaParty->preferred_name }}</div>
                            <div class="mt-1 text-xs text-zinc-500">
                                {{ ucfirst(str_replace('_', ' ', $route->relationship_type)) }} · {{ ucfirst($route->purpose) }}
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2"><x-priority-badge :rank="$route->priority" />@if ($route->is_primary)<x-status-badge tone="success" label="Primary route" />@else<x-status-badge tone="neutral" label="Alternative route" />@endif</div>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center justify-start gap-2 sm:justify-end">
                            <flux:button type="button" size="sm" variant="ghost" wire:click="view('{{ $route->id }}')"><flux:icon name="eye" class="app-button-icon size-4" /> View details</flux:button>
                            <flux:button type="button" size="sm" variant="outline" wire:click="edit('{{ $route->id }}')"><flux:icon name="pencil" class="app-button-icon size-4" /> Edit route</flux:button>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="remove('{{ $route->id }}')" wire:confirm="Remove this contact route?"><flux:icon name="trash" class="app-button-icon size-4" /> Remove route</flux:button>
                        </div>
                    </div>
                    @if ($viewingRouteId === $route->id)
                        <div class="mt-3 grid gap-3 rounded-lg border border-zinc-200 bg-white p-3 text-xs dark:border-zinc-700 dark:bg-zinc-900 sm:grid-cols-2">
                            <div>
                                <div class="font-medium text-zinc-500">Party to reach</div>
                                <div class="mt-1">{{ $route->party->preferred_name }}</div>
                            </div>
                            <div>
                                <div class="font-medium text-zinc-500">Reach through</div>
                                <div class="mt-1">{{ $route->viaParty->preferred_name }}</div>
                            </div>
                            <div>
                                <div class="font-medium text-zinc-500">Contact point</div>
                                <div class="mt-1">{{ $route->viaContact ? ucfirst($route->viaContact->type).' · '.$route->viaContact->value : 'Use the intermediary generally' }}</div>
                            </div>
                            <div>
                                <div class="font-medium text-zinc-500">Purpose and priority</div>
                                <div class="mt-2 flex flex-wrap gap-2"><x-priority-badge :rank="$route->priority" />@if ($route->is_primary)<x-status-badge tone="success" label="Primary" />@else<x-status-badge tone="neutral" label="Alternative" />@endif</div>
                            </div>
                            @if ($route->instructions)
                                <div class="sm:col-span-2">
                                    <div class="font-medium text-zinc-500">Instructions</div>
                                    <div class="mt-1 whitespace-pre-line">{{ $route->instructions }}</div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($routes->hasPages())
            <div class="mt-4">{{ $routes->links() }}</div>
        @endif
    @endif
</section>
