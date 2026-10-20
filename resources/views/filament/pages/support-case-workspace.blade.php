<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">{{ $supportCase->subject }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Profile: {{ $profile->name }} · Case status: {{ $supportCase->status->value }}</p>
                <p class="mt-3 max-w-3xl whitespace-pre-wrap text-sm">{{ $supportCase->reason }}</p>
            </div>
            @if ($grant)
                <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-900 dark:bg-green-950 dark:text-green-100">
                    Approved access expires {{ $grant->expires_at->toDateTimeString() }}
                </div>
            @endif
        </div>
    </section>

    @if (! $grant)
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
            Customer profile data is hidden until a lead approves an active support access grant.
        </div>
    @else
        @if ($canViewRecords)
        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Records and obligations</h2>
            @forelse ($records as $record)
                <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="font-medium">{{ $record['title'] }}</h3>
                    @forelse ($record['obligations'] as $obligation)
                        <div class="mt-3 border-l-2 border-gray-300 pl-3 dark:border-gray-600">
                            <p class="text-sm">{{ $obligation['title'] }} · {{ $obligation['status'] }}</p>
                            @if ($obligation['quantity_subject'])
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    Quantity subject · Description: {{ $obligation['quantity_subject']['description'] }} · Unit: {{ $obligation['quantity_subject']['unit'] }} · Total quantity: {{ $obligation['quantity_subject']['total_quantity'] }} · Fractionable: {{ $obligation['quantity_subject']['fractionable'] ? 'Yes' : 'No' }}
                                </p>
                            @endif
                            @foreach ($obligation['movements'] as $movement)
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    {{ $movement['entry'] }} · {{ $movement['amount_minor'] }} {{ $movement['currency'] }} · {{ $movement['occurred_on'] }}
                                </p>
                            @endforeach
                            @foreach ($obligation['quantity_returns'] as $quantityReturn)
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    Quantity returned · {{ $quantityReturn['quantity'] }} · {{ $quantityReturn['status'] }} · {{ $quantityReturn['occurred_on'] }}
                                </p>
                            @endforeach
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-gray-500">No obligations.</p>
                    @endforelse
                </article>
            @empty
                <p class="text-sm text-gray-500">No records.</p>
            @endforelse
        </section>

        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Attachments</h2>
            @forelse ($attachments as $attachment)
                <p class="text-sm">{{ $attachment['original_name'] }} · {{ $attachment['category'] }} · {{ $attachment['mime'] }} · {{ $attachment['size_bytes'] }} bytes · {{ $attachment['created_at'] }}</p>
            @empty
                <p class="text-sm text-gray-500">No attachments.</p>
            @endforelse
        </section>

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Parties</h2>
            @forelse ($parties as $party)
                <article class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <h3 class="font-medium">{{ $party['name'] }}</h3>
                    @foreach ($party['contacts'] as $contact)
                        <p class="mt-1 text-sm">{{ $contact['label'] }}: {{ $contact['value'] }}</p>
                    @endforeach
                    @foreach ($party['destinations'] as $destination)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Payment destination: {{ $destination }}</p>
                    @endforeach
                </article>
            @empty
                <p class="text-sm text-gray-500">No parties.</p>
            @endforelse
        </section>
        @endif

        @if ($canViewMembers)
        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Members</h2>
            @forelse ($members as $member)
                <p class="text-sm">{{ $member['name'] }} · {{ $member['email'] }} · {{ $member['role'] }}</p>
            @empty
                <p class="text-sm text-gray-500">No members.</p>
            @endforelse
        </section>
        @endif

        @if ($canViewMembers)
        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Invitations</h2>
            @forelse ($invites as $invite)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3 last:border-0 dark:border-gray-800">
                    <p class="text-sm">{{ $invite['email'] }} · {{ $invite['role'] }} · expires {{ $invite['expires_at'] }}</p>
                    @if ($invite['usable'] && auth()->user()?->can('profileInvite.revoke'))
                        <x-filament::button color="danger" size="sm" wire:click="revokeInvite('{{ $invite['id'] }}')" wire:confirm="Revoke this invitation?">
                            Revoke invitation
                        </x-filament::button>
                    @elseif ($invite['revoked_at'])
                        <span class="text-xs text-gray-500">Revoked {{ $invite['revoked_at'] }}</span>
                    @elseif ($invite['accepted_at'])
                        <span class="text-xs text-gray-500">Accepted {{ $invite['accepted_at'] }}</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">No invitations.</p>
            @endforelse
        </section>
        @endif

        @if ($canViewRecords)
        <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Imports</h2>
            @forelse ($imports as $import)
                <p class="text-sm">{{ $import['filename'] }} · {{ $import['row_count'] }} rows · {{ $import['created_at'] }}</p>
            @empty
                <p class="text-sm text-gray-500">No imports.</p>
            @endforelse
        </section>
        @endif
    @endif
</x-filament-panels::page>
