<div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
    <div>
        <a href="{{ route('people.index', $profile) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">{{ __('app.back_to_people') }}</a>
        <div class="app-eyebrow mt-6">{{ $profile->name }}</div>
        <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">{{ $party->display_name }}</flux:heading>
        <flux:text class="mt-2">{{ __('app.party_exposure_intro', ['kind' => __('app.party_kinds.'.$party->kind->value)]) }}</flux:text>
        @can('update', $party)
            <div class="mt-4">
                @if ($party->status === \App\Domain\Enums\PartyStatus::Active)
                    <flux:button wire:click="archive" :wire:confirm="__('app.archive_person_confirm')" variant="ghost" wire:loading.attr="disabled">{{ __('app.archive_person') }}</flux:button>
                @else
                    <flux:button wire:click="restore" :wire:confirm="__('app.restore_person_confirm')" variant="ghost" wire:loading.attr="disabled">{{ __('app.restore_person') }}</flux:button>
                @endif
            </div>
        @endcan
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-card rounded-2xl p-5"><flux:text>{{ __('app.open_parts') }}</flux:text><div class="mt-2 text-2xl font-semibold">{{ $exposure['open_count'] }}</div></div>
        <div class="app-card rounded-2xl p-5"><flux:text>{{ __('app.settled_parts') }}</flux:text><div class="mt-2 text-2xl font-semibold">{{ $exposure['settled_count'] }}</div></div>
    </div>

    @if ($offsetSuggestions !== [])
        <section class="app-card space-y-4 rounded-2xl p-5 sm:p-7">
            <div>
                <flux:heading size="lg">{{ __('app.offset') }}</flux:heading>
                <flux:text class="mt-1">{{ __('app.offset_intro') }}</flux:text>
            </div>
            <div class="space-y-3">
                @foreach ($offsetSuggestions as $suggestion)
                    <form wire:submit="applyOffset('{{ $suggestion['payable_id'] }}', '{{ $suggestion['receivable_id'] }}')" wire:key="offset-{{ $suggestion['key'] }}" class="grid gap-3 rounded-xl border border-zinc-200 p-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end dark:border-zinc-700">
                        <div class="text-sm">
                            <div class="font-medium">{{ $suggestion['payable_title'] }} ↔ {{ $suggestion['receivable_title'] }}</div>
                            <div class="mt-1 text-zinc-500">{{ $suggestion['currency'] }} · {{ __('app.suggested_amount') }} {{ \App\Domain\Money\Money::display($suggestion['amount_minor'], $suggestion['currency']) }}</div>
                        </div>
                        <flux:input wire:model="offsetAmounts.{{ $suggestion['key'] }}" label="{{ __('app.offset_amount') }}" inputmode="decimal" />
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('app.apply_offset') }}</flux:button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="app-metric app-metric-primary rounded-2xl p-5">
            <flux:text>{{ __('promises.to_pay') }}</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold">@forelse ($exposure['to_pay'] as $currency => $amount)<div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
        </div>
    <div class="app-metric app-metric-secondary rounded-2xl p-5">
            <flux:text>{{ __('promises.to_receive') }}</flux:text>
            <div class="mt-3 space-y-1 text-2xl font-semibold text-cyan-800">@forelse ($exposure['to_receive'] as $currency => $amount)<div>{{ \App\Domain\Money\Money::display($amount, $currency) }}</div>@empty<div>—</div>@endforelse</div>
        </div>
    </div>

    @if ($party->contacts->isNotEmpty() || $showContactForm || auth()->user()->can('manageContacts', $party))
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <div><flux:heading size="lg">{{ __('app.contacts') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('app.contacts_intro') }}</flux:text></div>
                @can('manageContacts', $party)
                    @if (! $showContactForm)
                        <flux:button wire:click="$set('showContactForm', true)" wire:loading.attr="disabled" variant="ghost">{{ __('app.add_contact') }}</flux:button>
                    @endif
                @endcan
            </div>
            @if ($showContactForm)
                @can('manageContacts', $party)
                    <form wire:submit="addContact" class="grid gap-4 border-b border-zinc-200/80 px-5 py-5 sm:grid-cols-[10rem_minmax(0,1fr)_auto] sm:items-end dark:border-zinc-700/80">
                        <flux:input wire:model="contactLabel" :label="__('app.label')" :placeholder="__('app.contact_label_placeholder')" required />
                        <flux:input wire:model="contactValue" :label="__('app.value')" :placeholder="__('app.contact_value_placeholder')" required />
                        <div class="flex items-center gap-3 sm:pb-2"><label class="flex items-center gap-2 text-sm"><input wire:model="contactIsPrimary" type="checkbox" class="rounded border-zinc-300 text-emerald-600" /> {{ __('app.primary') }}</label><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('app.add') }}</flux:button></div>
                    </form>
                @endcan
            @endif
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($party->contacts as $contact)
                    <div wire:key="contact-{{ $contact->id }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                        <div><div class="font-medium">{{ $contact->label }} @if ($contact->is_primary)<span class="ms-2 text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ __('app.primary') }}</span>@endif</div><div class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $contact->value }}</div></div>
                        @can('manageContacts', $party)
                            <div class="flex gap-3 text-sm"><button type="button" wire:click="setPrimaryContact('{{ $contact->id }}')" wire:loading.attr="disabled" class="font-semibold text-emerald-700 hover:underline">{{ $contact->is_primary ? __('app.primary') : __('app.make_primary') }}</button><button type="button" wire:click="removeContact('{{ $contact->id }}')" wire:confirm="{{ __('app.remove_contact_confirm') }}" wire:loading.attr="disabled" class="font-semibold text-red-700 hover:underline">{{ __('app.remove') }}</button></div>
                        @endcan
                    </div>
                @empty
                    @can('manageContacts', $party)
                        @if (! $showContactForm)<div class="px-5 py-8 text-sm text-zinc-500">{{ __('app.no_contacts') }}</div>@endif
                    @endcan
                @endforelse
            </div>
        </section>
    @endif

    @if ($party->paymentDestinations->isNotEmpty() || $showDestinationForm || auth()->user()->can('managePaymentDestinations', $party))
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <div><flux:heading size="lg">{{ __('app.payment_destinations') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('app.payment_destinations_intro') }}</flux:text></div>
                @can('managePaymentDestinations', $party)
                    @if (! $showDestinationForm)
                        <flux:button wire:click="$set('showDestinationForm', true)" wire:loading.attr="disabled" variant="ghost">{{ __('app.add_destination') }}</flux:button>
                    @endif
                @endcan
            </div>
            @if ($showDestinationForm)
                @can('managePaymentDestinations', $party)
                    <form wire:submit="addDestination" class="grid gap-4 border-b border-zinc-200/80 px-5 py-5 sm:grid-cols-3 sm:items-end dark:border-zinc-700/80">
                        <flux:select wire:model="destinationKind" :label="__('app.kind')" required>
                            @foreach ($destinationKinds as $kind)<flux:select.option value="{{ $kind->value }}">{{ __('app.destination_kinds.'.$kind->value) }}</flux:select.option>@endforeach
                        </flux:select>
                        <flux:input wire:model="destinationLabel" :label="__('app.label')" :placeholder="__('app.main_account')" required />
                        <flux:input wire:model="destinationDetails" :label="__('app.details')" :placeholder="__('app.account_reference_placeholder')" required />
                        <div class="flex gap-3 sm:col-span-3 sm:justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('app.add_destination') }}</flux:button></div>
                    </form>
                @endcan
            @endif
            <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
                @forelse ($destinations as $destination)
                    <div wire:key="destination-{{ $destination['id'] }}" class="flex flex-wrap items-center justify-between gap-4 px-5 py-4">
                        <div><div class="font-medium">{{ $destination['label'] }} <span class="ms-2 text-xs uppercase tracking-wide text-zinc-500">{{ $destination['kind'] }}</span></div><div class="mt-1 font-mono text-sm text-zinc-600 dark:text-zinc-300">{{ $destination['masked'] }}</div>@if ($destination['is_verified'])<div class="mt-1 text-xs font-semibold text-emerald-700">{{ $destination['verified_by'] ? __('app.verified_by', ['name' => $destination['verified_by']]) : __('app.verified') }}</div>@else<div class="mt-1 text-xs text-amber-700">{{ __('app.not_verified') }}</div>@endif</div>
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            @can('revealPaymentDestination', $party)
                                <button type="button" wire:click="revealDestination('{{ $destination['id'] }}')" wire:loading.attr="disabled" class="font-semibold text-emerald-700 hover:underline">{{ __('app.reveal_once') }}</button>
                            @endcan
                            @can('managePaymentDestinations', $party)
                                @if (! $destination['is_verified'])<button type="button" wire:click="verifyDestination('{{ $destination['id'] }}')" wire:loading.attr="disabled" class="font-semibold text-zinc-600 hover:underline dark:text-zinc-300">{{ __('app.verify') }}</button>@endif
                                <button type="button" wire:click="removeDestination('{{ $destination['id'] }}')" wire:confirm="{{ __('app.remove_destination_confirm') }}" wire:loading.attr="disabled" class="font-semibold text-red-700 hover:underline">{{ __('app.remove') }}</button>
                            @endcan
                        </div>
                        @if ($revealedDestinationId === $destination['id'])
                            <div class="w-full rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 font-mono text-sm text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-100">{{ __('app.revealed_this_render', ['value' => $revealedDestinationValue]) }}</div>
                        @endif
                    </div>
                @empty
                    @can('managePaymentDestinations', $party)
                        @if (! $showDestinationForm)<div class="px-5 py-8 text-sm text-zinc-500">{{ __('app.no_destinations') }}</div>@endif
                    @endcan
                @endforelse
            </div>
        </section>
    @endif

    @if ($assists->isNotEmpty() || $assistedBy->isNotEmpty() || $showRelationshipForm || auth()->user()->can('manageRelationships', $party))
        <section class="app-card overflow-hidden rounded-2xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                <div><flux:heading size="lg">{{ __('app.relationships') }}</flux:heading><flux:text class="mt-1 text-sm">{{ __('app.relationships_intro') }}</flux:text></div>
                @can('manageRelationships', $party)
                    @if (! $showRelationshipForm)<flux:button wire:click="$set('showRelationshipForm', true)" wire:loading.attr="disabled" variant="ghost">{{ __('app.add_relationship') }}</flux:button>@endif
                @endcan
            </div>
            @if ($showRelationshipForm)
                @can('manageRelationships', $party)
                    <form wire:submit="addRelationship" class="space-y-4 border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80">
                        <div class="relative">
                            <flux:input wire:model.live.debounce.250ms="relationshipSearch" :label="__('app.relationship_person')" :placeholder="__('app.people_search')" required />
                            @if ($relationshipMatches->isNotEmpty())
                                <div class="absolute z-10 mt-1 w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    @foreach ($relationshipMatches as $match)<button type="button" wire:click="selectRelationshipParty('{{ $match->id }}')" wire:loading.attr="disabled" class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">{{ $match->display_name }} <span class="text-zinc-500">· {{ __('app.party_kinds.'.$match->kind->value) }}</span></button>@endforeach
                                </div>
                            @endif
                        </div>
                        <flux:select wire:model="relationshipKind" :label="__('app.relationship')" required>
                            @foreach ($relationshipKinds as $kind)<flux:select.option value="{{ $kind->value }}">{{ __('app.relationship_kinds.'.$kind->value) }}</flux:select.option>@endforeach
                        </flux:select>
                        <div class="flex justify-end"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('app.link_person') }}</flux:button></div>
                    </form>
                @endcan
            @endif
            <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">
                <div>
                    <flux:heading size="sm">{{ __('app.assists') }}</flux:heading>
                    <div class="mt-3 space-y-3">
                        @forelse ($assists as $assist)
                            <div wire:key="relationship-out-{{ $assist['id'] }}" class="flex items-start justify-between gap-3 text-sm"><a href="{{ route('people.show', ['profile' => $profile, 'party' => $assist['to_id']]) }}" wire:navigate class="hover:underline">{{ $assist['from_name'] }} — {{ $assist['kind'] }} → {{ $assist['to_name'] }}</a>@can('manageRelationships', $party)<button type="button" wire:click="removeRelationship('{{ $assist['id'] }}')" wire:confirm="{{ __('app.remove_relationship_confirm') }}" wire:loading.attr="disabled" class="shrink-0 font-semibold text-red-700 hover:underline">{{ __('app.remove') }}</button>@endcan</div>
                        @empty
                            <div class="text-sm text-zinc-500">{{ __('app.no_outgoing_links') }}</div>
                        @endforelse
                    </div>
                </div>
                <div>
                    <flux:heading size="sm">{{ __('app.assisted_by') }}</flux:heading>
                    <div class="mt-3 space-y-3">
                        @forelse ($assistedBy as $assist)
                            <div wire:key="relationship-in-{{ $assist['id'] }}" class="flex items-start justify-between gap-3 text-sm"><a href="{{ route('people.show', ['profile' => $profile, 'party' => $assist['from_id']]) }}" wire:navigate class="hover:underline">{{ $assist['from_name'] }} — {{ $assist['kind'] }} → {{ $assist['to_name'] }}</a>@can('manageRelationships', $party)<button type="button" wire:click="removeRelationship('{{ $assist['id'] }}')" wire:confirm="{{ __('app.remove_relationship_confirm') }}" wire:loading.attr="disabled" class="shrink-0 font-semibold text-red-700 hover:underline">{{ __('app.remove') }}</button>@endcan</div>
                        @empty
                            <div class="text-sm text-zinc-500">{{ __('app.no_incoming_links') }}</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="app-card overflow-hidden rounded-2xl">
        <div class="border-b border-zinc-200/80 px-5 py-5 dark:border-zinc-700/80"><flux:heading size="lg">{{ __('app.promise_records') }}</flux:heading></div>
        <div class="divide-y divide-zinc-200/80 dark:divide-zinc-700/80">
            @forelse ($records as $record)
                <a href="{{ route('promises.show', [$profile, $record]) }}" wire:navigate class="block px-5 py-4 hover:bg-zinc-50 dark:hover:bg-zinc-900/60">
                    <div class="font-medium">{{ $record->title }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ $record->obligations->count() }} {{ $record->obligations->count() === 1 ? __('promises.part') : __('promises.parts') }}</div>
                </a>
            @empty
                <div class="px-5 py-10 text-sm text-zinc-500">{{ __('app.no_active_records') }}</div>
            @endforelse
        </div>
    </section>
</div>
