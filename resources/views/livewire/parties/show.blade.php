<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('people.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">← Back to people</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ $party->display_name }}</flux:heading>
        <flux:text class="mt-2">{{ ucfirst($party->kind->value) }} exposure across your promise records.</flux:text>
        @can('update', $party)
            <div class="mt-4">
                @if ($party->status === \App\Domain\Enums\PartyStatus::Active)
                    <flux:button wire:click="archive" wire:confirm="Archive this person? They must have no open promises." variant="ghost" wire:loading.attr="disabled">Archive person</flux:button>
                @else
                    <flux:button wire:click="restore" wire:confirm="Restore this person?" variant="ghost" wire:loading.attr="disabled">Restore person</flux:button>
                @endif
            </div>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-card rounded-2xl p-5"><flux:text>Open parts</flux:text><div class="mt-2 text-2xl font-semibold">{{ $exposure['open_count'] }}</div></div>
        <div class="app-card rounded-2xl p-5"><flux:text>Settled parts</flux:text><div class="mt-2 text-2xl font-semibold">{{ $exposure['settled_count'] }}</div></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-metric app-metric-primary rounded-2xl p-5">
            <flux:text>To pay</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold">@forelse ($exposure['to_pay'] as $currency => $amount)<div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
        </div>
    <div class="app-metric app-metric-secondary rounded-2xl p-5">
            <flux:text>To receive</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold text-cyan-800">@forelse ($exposure['to_receive'] as $currency => $amount)<div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
        </div>
    </div>

    @if ($party->contacts->isNotEmpty() || $showContactForm || auth()->user()->can('manageContacts', $party))
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <div><flux:heading size="lg">Contacts</flux:heading><flux:text class="mt-1 text-sm">Direct ways to reach this person.</flux:text></div>
                @can('manageContacts', $party)
                    @if (! $showContactForm)
                        <flux:button wire:click="$set('showContactForm', true)" wire:loading.attr="disabled" variant="ghost">Add contact</flux:button>
                    @endif
                @endcan
            </div>
            @if ($showContactForm)
                @can('manageContacts', $party)
                    <form wire:submit="addContact" class="grid gap-4 border-b border-zinc-200/80 px-5 py-5 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-end dark:border-zinc-700/80">
                        <flux:input wire:model="contactLabel" label="Label" placeholder="mobile, WhatsApp" required />
                        <flux:input wire:model="contactValue" label="Value" placeholder="Contact details" required />
                        <div class="flex items-center gap-3 sm:pb-2"><label class="flex items-center gap-2 text-sm"><input wire:model="contactIsPrimary" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> Primary</label><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Add</flux:button></div>
                    </form>
                @endcan
            @endif
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($party->contacts as $contact)
                    <div wire:key="contact-{{ $contact->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div><div class="font-medium">{{ $contact->label }} @if ($contact->is_primary)<span class="ms-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">Primary</span>@endif</div><div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $contact->value }}</div></div>
                        @can('manageContacts', $party)
                            <div class="flex gap-3 text-sm"><button type="button" wire:click="setPrimaryContact('{{ $contact->id }}')" wire:loading.attr="disabled" class="font-semibold text-emerald-700 hover:underline">{{ $contact->is_primary ? 'Primary' : 'Make primary' }}</button><button type="button" wire:click="removeContact('{{ $contact->id }}')" wire:confirm="Remove this contact?" wire:loading.attr="disabled" class="font-semibold text-red-700 hover:underline">Remove</button></div>
                        @endcan
                    </div>
                @empty
                    @can('manageContacts', $party)
                        @if (! $showContactForm)<div class="px-5 py-8 text-sm text-zinc-500">No contacts yet. Add a direct way to reach them.</div>@endif
                    @endcan
                @endforelse
            </div>
        </section>
    @endif

    @if ($party->paymentDestinations->isNotEmpty() || $showDestinationForm || auth()->user()->can('managePaymentDestinations', $party))
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <div><flux:heading size="lg">Payment destinations</flux:heading><flux:text class="mt-1 text-sm">Reference details only. Recording a payment never links to a destination automatically.</flux:text></div>
                @can('managePaymentDestinations', $party)
                    @if (! $showDestinationForm)
                        <flux:button wire:click="$set('showDestinationForm', true)" wire:loading.attr="disabled" variant="ghost">Add destination</flux:button>
                    @endif
                @endcan
            </div>
            @if ($showDestinationForm)
                @can('managePaymentDestinations', $party)
                    <form wire:submit="addDestination" class="grid gap-4 border-b border-zinc-200/80 px-5 py-5 sm:grid-cols-3 sm:items-end dark:border-zinc-700/80">
                        <flux:select wire:model="destinationKind" label="Kind" required>
                            @foreach ($destinationKinds as $kind)<flux:select.option value="{{ $kind->value }}">{{ $kind->label() }}</flux:select.option>@endforeach
                        </flux:select>
                        <flux:input wire:model="destinationLabel" label="Label" placeholder="Main account" required />
                        <flux:input wire:model="destinationDetails" label="Details" placeholder="Account number or reference" required />
                        <div class="flex gap-3 sm:col-span-3 sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Add destination</flux:button></div>
                    </form>
                @endcan
            @endif
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($destinations as $destination)
                    <div wire:key="destination-{{ $destination['id'] }}" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                        <div><div class="font-medium">{{ $destination['label'] }} <span class="ms-2 text-xs uppercase tracking-wide text-zinc-500">{{ $destination['kind'] }}</span></div><div class="mt-1 font-mono text-sm text-zinc-600 dark:text-zinc-300">{{ $destination['masked'] }}</div>@if ($destination['is_verified'])<div class="mt-1 text-xs font-semibold text-emerald-700">Verified{{ $destination['verified_by'] ? ' by '.$destination['verified_by'] : '' }}</div>@else<div class="mt-1 text-xs text-amber-700">Not verified</div>@endif</div>
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            @can('revealPaymentDestination', $party)
                                <button type="button" wire:click="revealDestination('{{ $destination['id'] }}')" wire:loading.attr="disabled" class="font-semibold text-emerald-700 hover:underline">Reveal once</button>
                            @endcan
                            @can('managePaymentDestinations', $party)
                                @if (! $destination['is_verified'])<button type="button" wire:click="verifyDestination('{{ $destination['id'] }}')" wire:loading.attr="disabled" class="font-semibold text-zinc-600 hover:underline dark:text-zinc-300">Verify</button>@endif
                                <button type="button" wire:click="removeDestination('{{ $destination['id'] }}')" wire:confirm="Remove this destination?" wire:loading.attr="disabled" class="font-semibold text-red-700 hover:underline">Remove</button>
                            @endcan
                        </div>
                        @if ($revealedDestinationId === $destination['id'])
                            <div class="w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-mono text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">Revealed for this render: {{ $revealedDestinationValue }}</div>
                        @endif
                    </div>
                @empty
                    @can('managePaymentDestinations', $party)
                        @if (! $showDestinationForm)<div class="px-5 py-8 text-sm text-zinc-500">No payment destinations yet. Add one as reference context only.</div>@endif
                    @endcan
                @endforelse
            </div>
        </section>
    @endif

    @if ($assists->isNotEmpty() || $assistedBy->isNotEmpty() || $showRelationshipForm || auth()->user()->can('manageRelationships', $party))
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <div><flux:heading size="lg">Relationships</flux:heading><flux:text class="mt-1 text-sm">Directed links keep who assists whom explicit.</flux:text></div>
                @can('manageRelationships', $party)
                    @if (! $showRelationshipForm)<flux:button wire:click="$set('showRelationshipForm', true)" wire:loading.attr="disabled" variant="ghost">Add relationship</flux:button>@endif
                @endcan
            </div>
            @if ($showRelationshipForm)
                @can('manageRelationships', $party)
                    <form wire:submit="addRelationship" class="space-y-4 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                        <div class="relative">
                            <flux:input wire:model.live.debounce.250ms="relationshipSearch" label="Person" placeholder="Search people in this profile" required />
                            @if ($relationshipMatches->isNotEmpty())
                                <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    @foreach ($relationshipMatches as $match)<button type="button" wire:click="selectRelationshipParty('{{ $match->id }}')" wire:loading.attr="disabled" class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">{{ $match->display_name }} <span class="text-zinc-500">· {{ $match->kind->value }}</span></button>@endforeach
                                </div>
                            @endif
                        </div>
                        <flux:select wire:model="relationshipKind" label="Relationship" required>
                            @foreach ($relationshipKinds as $kind)<flux:select.option value="{{ $kind->value }}">{{ $kind->label() }}</flux:select.option>@endforeach
                        </flux:select>
                        <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Link person</flux:button></div>
                    </form>
                @endcan
            @endif
            <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <flux:heading size="sm">Assists</flux:heading>
                    <div class="mt-3 space-y-3">
                        @forelse ($assists as $assist)
                            <div wire:key="relationship-out-{{ $assist['id'] }}" class="flex items-start justify-between gap-3 text-sm"><a href="{{ route('people.show', ['profile' => $profile, 'party' => $assist['to_id']]) }}" wire:navigate class="hover:underline">{{ $assist['from_name'] }} — {{ $assist['kind'] }} → {{ $assist['to_name'] }}</a>@can('manageRelationships', $party)<button type="button" wire:click="removeRelationship('{{ $assist['id'] }}')" wire:confirm="Remove this relationship?" wire:loading.attr="disabled" class="shrink-0 font-semibold text-red-700 hover:underline">Remove</button>@endcan</div>
                        @empty
                            <div class="text-sm text-zinc-500">No outgoing links.</div>
                        @endforelse
                    </div>
                </div>
                <div>
                    <flux:heading size="sm">Assisted by</flux:heading>
                    <div class="mt-3 space-y-3">
                        @forelse ($assistedBy as $assist)
                            <div wire:key="relationship-in-{{ $assist['id'] }}" class="flex items-start justify-between gap-3 text-sm"><a href="{{ route('people.show', ['profile' => $profile, 'party' => $assist['from_id']]) }}" wire:navigate class="hover:underline">{{ $assist['from_name'] }} — {{ $assist['kind'] }} → {{ $assist['to_name'] }}</a>@can('manageRelationships', $party)<button type="button" wire:click="removeRelationship('{{ $assist['id'] }}')" wire:confirm="Remove this relationship?" wire:loading.attr="disabled" class="shrink-0 font-semibold text-red-700 hover:underline">Remove</button>@endcan</div>
                        @empty
                            <div class="text-sm text-zinc-500">No incoming links.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">Promise records</flux:heading></div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($records as $record)
                <a href="{{ route('promises.show', [$profile, $record]) }}" wire:navigate class="block px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                    <div class="font-medium">{{ $record->title }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ $record->obligations->count() }} {{ $record->obligations->count() === 1 ? 'part' : 'parts' }}</div>
                </a>
            @empty
                <div class="px-5 py-10 text-sm text-zinc-500">No active promise records.</div>
            @endforelse
        </div>
    </section>
</div>
