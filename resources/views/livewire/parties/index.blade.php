<div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-7">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="app-page-icon hidden shrink-0 sm:inline-flex"><flux:icon name="user-group" class="size-6" /></span>
            <div>
                <span class="app-eyebrow">{{ $profile->name }}</span>
                <flux:heading size="xl" class="mt-2 text-3xl tracking-tight">Parties</flux:heading>
                <flux:text class="mt-2 max-w-2xl leading-6">Keep people, organisations, households, estates, and other parties in one trusted directory. A party can appear in many records with different roles.</flux:text>
            </div>
        </div>
        <flux:select wire:model.live="profileId" class="w-48" aria-label="Financial profile">
            @foreach ($profiles as $item)<flux:select.option :value="$item->id">{{ $item->name }}</flux:select.option>@endforeach
        </flux:select>
    </div>

    @if (session('party-created'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('party-created') }}</div>
    @endif
    @if (session('party-updated'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('party-updated') }}</div>
    @endif
    @if (session('contact-added'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('contact-added') }}</div>
    @endif
    @if (session('contact-updated'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('contact-updated') }}</div>
    @endif
    @if (session('payment-added'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('payment-added') }}</div>
    @endif
    @if (session('payment-updated'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('payment-updated') }}</div>
    @endif
    @if (session('payment-archived'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('payment-archived') }}</div>
    @endif

    @if ($canManage)
        <form wire:key="party-form-{{ $editingPartyId ?: 'new' }}" wire:submit="{{ $editingPartyId ? 'updateParty' : 'save' }}" class="app-card space-y-5 rounded-2xl p-6">
            <div>
                <flux:heading size="lg">{{ $editingPartyId ? 'Edit party' : 'Add a party' }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ $editingPartyId ? 'Keep the directory current. Existing records continue to point to this same party.' : 'Only add the details you actually need. You can enrich this profile later.' }}</flux:text>
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="name" label="Preferred name" placeholder="e.g. Aminah or Maybank" required />
                <flux:select wire:model="kind" label="Party type">
                    <flux:select.option value="individual">Person</flux:select.option>
                    <flux:select.option value="organization">Organisation</flux:select.option>
                    <flux:select.option value="group">Group or household</flux:select.option>
                    <flux:select.option value="estate_or_trust">Estate or trust</flux:select.option>
                    <flux:select.option value="unidentified">Unidentified for now</flux:select.option>
                </flux:select>
                <flux:input wire:model="legalName" label="Legal name (optional)" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <flux:input wire:model="email" type="email" label="Primary email (optional)" />
                <flux:input wire:model="phone" label="Primary phone (optional)" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <flux:input wire:model="addressLine1" label="Address (optional)" />
                <flux:input wire:model="city" label="City" />
                <flux:input wire:model="countryCode" label="Country code" maxlength="2" />
            </div>
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                @if ($editingPartyId)<flux:button type="button" variant="ghost" wire:click="cancelPartyEdit"><flux:icon name="x-mark" class="app-button-icon size-4" /> Cancel</flux:button>@endif
                <flux:button type="submit" variant="primary" class="min-w-max"><flux:icon name="{{ $editingPartyId ? 'check' : 'plus' }}" class="app-button-icon size-4" /> {{ $editingPartyId ? 'Save party changes' : 'Add party' }}</flux:button>
            </div>
        </form>
    @endif

    @if ($canManage)
        <livewire:parties.manage-contact-routes :profile="$profile" :key="'contact-routes-'.$profile->id" />
    @endif

    <section class="grid gap-4 lg:grid-cols-2">
        @forelse ($parties as $party)
            <article class="app-card rounded-2xl p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <flux:icon name="user-circle" class="size-5 text-emerald-700" />
                            <flux:heading size="lg">{{ $party->preferred_name }}</flux:heading>
                        </div>
                        <div class="mt-1 text-sm text-zinc-500">{{ $party->kindLabel() }}@if ($party->legal_name) · {{ $party->legal_name }}@endif · {{ $party->record_parties_count }} record{{ $party->record_parties_count === 1 ? '' : 's' }}</div>
                    </div>
                    @if ($canManage)
                        <div class="flex flex-wrap justify-end gap-2">
                            <flux:button type="button" size="sm" variant="ghost" wire:click="editParty('{{ $party->id }}')"><flux:icon name="pencil" class="app-button-icon size-4" /> Edit party</flux:button>
                            <flux:button type="button" size="sm" variant="ghost" wire:click="archive('{{ $party->id }}')" wire:confirm="Archive this party from future selections? Existing records will keep their history."><flux:icon name="archive-box" class="app-button-icon size-4" /> Archive</flux:button>
                        </div>
                    @endif
                </div>
                @if ($canManage && $party->contacts->isNotEmpty())
                    <div class="mt-4 space-y-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        @foreach ($party->contacts as $contact)
                            <div class="flex items-center justify-between gap-3 text-sm text-zinc-600 dark:text-zinc-300">
                                <div class="flex items-center gap-2"><flux:icon name="{{ $contact->type === 'email' ? 'envelope' : 'phone' }}" class="size-4 text-zinc-400" /> <span>{{ $contact->label }} · {{ ucfirst($contact->type) }} · {{ $contact->value }}</span></div>
                                <flux:button type="button" size="sm" variant="ghost" wire:click="editContact('{{ $contact->id }}')"><flux:icon name="pencil" class="app-button-icon size-4" /> Edit contact</flux:button>
                            </div>
                        @endforeach
                    </div>
                @elseif ($canManage)
                    <p class="mt-4 border-t border-zinc-100 pt-4 text-xs text-zinc-500 dark:border-zinc-800">No contact details saved yet.</p>
                @elseif (! $canManage)
                    <p class="mt-4 border-t border-zinc-100 pt-4 text-xs text-zinc-500 dark:border-zinc-800">Contact details are restricted to party managers.</p>
                @endif
                @if ($canManage && $party->addresses->isNotEmpty())
                    <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Addresses</div>
                        @foreach ($party->addresses as $address)
                            <div class="mt-2 flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                                <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-zinc-400" />
                                <span>{{ $address->label }} · {{ collect([$address->address_line_1, $address->city, $address->region, $address->postal_code, $address->country_code])->filter()->implode(', ') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
                @if ($canManage)
                    <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        @if ($contactPartyId !== $party->id)
                            <flux:button type="button" size="sm" variant="outline" class="min-w-max" wire:click="beginContact('{{ $party->id }}')"><flux:icon name="plus" class="app-button-icon size-4" /> Add contact detail</flux:button>
                        @else
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium">{{ $editingContactId ? 'Edit contact detail' : 'Add contact detail' }}</div>
                                        <p class="mt-1 text-xs text-zinc-500">Use a label such as Work mobile or Personal email so routes remain easy to understand.</p>
                                    </div>
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="cancelContactEdit"><flux:icon name="x-mark" class="app-button-icon size-4" /> Cancel</flux:button>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                    <flux:input wire:model="contactLabel" label="Label" placeholder="e.g. Work mobile" required />
                                    <flux:select wire:model="contactType" label="Type"><flux:select.option value="email">Email</flux:select.option><flux:select.option value="phone">Phone</flux:select.option><flux:select.option value="whatsapp">WhatsApp</flux:select.option><flux:select.option value="telegram">Telegram</flux:select.option><flux:select.option value="website">Website</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
                                    <flux:input wire:model="contactValue" label="Contact value" placeholder="Email, number, or URL" required />
                                </div>
                                <div class="mt-4 flex justify-end"><flux:button type="button" wire:click="addContactFor('{{ $party->id }}')" variant="primary" class="min-w-max"><flux:icon name="{{ $editingContactId ? 'pencil' : 'check' }}" class="app-button-icon size-4" /> {{ $editingContactId ? 'Update contact' : 'Save contact' }}</flux:button></div>
                            </div>
                        @endif
                    </div>
                    <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">Payment destinations</div>
                                <p class="mt-1 text-xs text-zinc-500">Keep more than one route when this party accepts different banks, wallets, or delivery methods.</p>
                            </div>
                            @if ($paymentPartyId !== $party->id)
                                <flux:button type="button" size="sm" variant="outline" class="min-w-max" wire:click="beginPaymentDestination('{{ $party->id }}')"><flux:icon name="plus" class="app-button-icon size-4" /> Add destination</flux:button>
                            @endif
                        </div>
                        @forelse ($party->paymentDestinations->where('status', 'active') as $destination)
                            <div class="mb-2 rounded-lg bg-zinc-50 px-3 py-3 text-xs text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-300">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $destination->label ?: 'Payment destination' }}</div>
                                        <div class="mt-1">{{ str_replace('_', ' ', ucfirst($destination->method)) }}@if ($destination->provider) · {{ $destination->provider }}@endif · {{ $destination->maskedIdentifier() }} · {{ $destination->currency ?: 'Currency not set' }}</div>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="viewPaymentDestination('{{ $destination->id }}')"><flux:icon name="{{ $viewingPaymentDestinationId === $destination->id ? 'eye-slash' : 'eye' }}" class="app-button-icon size-4" /> {{ $viewingPaymentDestinationId === $destination->id ? 'Hide details' : 'View details' }}</flux:button>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="editPaymentDestination('{{ $destination->id }}')"><flux:icon name="pencil" class="app-button-icon size-4" /> Edit destination</flux:button>
                                        <flux:button type="button" size="sm" variant="ghost" wire:click="archivePaymentDestination('{{ $destination->id }}')" wire:confirm="Archive this payment destination? Existing payment instructions keep their recorded snapshot."><flux:icon name="archive-box" class="app-button-icon size-4" /> Archive</flux:button>
                                    </div>
                                </div>
                                @if ($viewingPaymentDestinationId === $destination->id)
                                    <div class="mt-3 grid gap-2 border-t border-zinc-200 pt-3 sm:grid-cols-2 dark:border-zinc-700">
                                        <div><span class="font-medium">Account holder:</span> {{ $destination->account_holder_name ?: 'Not recorded' }}</div>
                                        <div><span class="font-medium">Currency:</span> {{ $destination->currency ?: 'Not set' }}</div>
                                        <div><span class="font-medium">Reference:</span> {{ $destination->reference_template ?: 'No template' }}</div>
                                        <div><span class="font-medium">Verification:</span> {{ ucfirst($destination->verification_status) }}</div>
                                        <div class="sm:col-span-2">Full identifiers stay hidden here. Edit the destination when the account or wallet details change.</div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="mb-2 text-xs text-zinc-500">None saved.</p>
                        @endforelse
                        @if ($paymentPartyId === $party->id)
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-medium text-emerald-950 dark:text-emerald-100">{{ $editingPaymentDestinationId ? 'Edit payment destination' : 'Add payment destination' }}</div>
                                        <p class="mt-1 text-xs text-emerald-900/70 dark:text-emerald-100/70">Use a recognisable label so you can choose the right destination later. Identifiers are encrypted and only the last four characters are shown after saving.</p>
                                    </div>
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="cancelPaymentDestinationEdit"><flux:icon name="x-mark" class="app-button-icon size-4" /> Cancel</flux:button>
                                </div>
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <flux:input wire:model="paymentLabel" label="Destination name" placeholder="e.g. Maybank main account" required />
                                    <flux:select wire:model.live="paymentMethod" label="Method"><flux:select.option value="bank_account">Bank account</flux:select.option><flux:select.option value="e_wallet">E-wallet</flux:select.option><flux:select.option value="payment_provider">Payment provider</flux:select.option><flux:select.option value="cash">Cash</flux:select.option><flux:select.option value="crypto_wallet">Crypto wallet</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
                                    <flux:select wire:model="paymentCurrency" label="Currency"><flux:select.option value="">Not set</flux:select.option>@foreach ($currencies as $code => $label)<flux:select.option :value="$code">{{ $code }} · {{ $label }}</flux:select.option>@endforeach</flux:select>
                                    <flux:input wire:model="paymentProvider" label="Provider (optional)" placeholder="e.g. Maybank" />
                                    <flux:input wire:model="paymentAccountHolder" label="Account holder (optional)" />
                                    <div>
                                        <flux:input wire:model="paymentAccountIdentifier" label="Account / wallet identifier" placeholder="Stored encrypted; only last 4 shown" :disabled="$paymentMethod === 'cash'" />
                                        @if ($paymentMethod === 'cash')<p class="mt-1 text-xs text-zinc-500">Cash has no account identifier.</p>@elseif ($editingPaymentDestinationId)<p class="mt-1 text-xs text-zinc-500">Leave blank to keep the saved identifier. Changing the method requires entering it again.</p>@endif
                                    </div>
                                    <flux:input wire:model="paymentReference" label="Reference template (optional)" />
                                </div>
                                <div class="mt-4 flex justify-end"><flux:button type="button" wire:click="addPaymentDestinationFor('{{ $party->id }}')" variant="primary" class="min-w-max"><flux:icon name="{{ $editingPaymentDestinationId ? 'pencil' : 'check' }}" class="app-button-icon size-4" /> {{ $editingPaymentDestinationId ? 'Update destination' : 'Save destination' }}</flux:button></div>
                            </div>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="app-card rounded-2xl border-dashed px-6 py-14 text-center lg:col-span-2">
                <span class="app-icon-badge mx-auto size-11"><flux:icon name="user-group" class="size-5" /></span>
                <flux:heading size="lg" class="mt-3">No parties yet</flux:heading>
                <flux:text class="mt-2">Add the people and organisations connected to your obligations.</flux:text>
            </div>
        @endforelse
    </section>
    @if ($parties->hasPages())
        <div>{{ $parties->links() }}</div>
    @endif
</div>
