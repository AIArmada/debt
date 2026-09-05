<section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
        <flux:heading size="lg">Receiving accounts</flux:heading>
        <flux:text class="mt-1 text-sm">Optional places where incoming collections may arrive. These are separate from a party’s payment destinations.</flux:text>
    </div>
    @if (session('collection-account-created'))<div class="mx-5 mt-5 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('collection-account-created') }}</div>@endif
    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse ($accounts as $account)
            <div class="flex items-center justify-between gap-3 px-5 py-4"><div><div class="font-medium">{{ $account->label }}</div><div class="mt-1 text-sm text-zinc-500">{{ ucfirst(str_replace('_', ' ', $account->method)) }}@if ($account->provider) · {{ $account->provider }}@endif · {{ $account->currency ?: 'Currency not set' }} · {{ $account->maskedIdentifier() }}</div></div><flux:button size="sm" variant="ghost" wire:click="archive('{{ $account->id }}')" wire:confirm="Archive this receiving account? Existing collection history will stay intact.">Archive</flux:button></div>
        @empty
            <div class="px-5 py-6 text-sm text-zinc-500">No receiving accounts saved yet.</div>
        @endforelse
    </div>
    <form wire:submit="save" class="grid gap-4 border-t border-zinc-200 px-5 py-5 sm:grid-cols-2 dark:border-zinc-700">
        <flux:input wire:model="label" label="Label" placeholder="e.g. Maybank collections" required />
        <flux:select wire:model="method" label="Method"><flux:select.option value="bank_account">Bank account</flux:select.option><flux:select.option value="e_wallet">E-wallet</flux:select.option><flux:select.option value="payment_provider">Payment provider</flux:select.option><flux:select.option value="cash">Cash</flux:select.option><flux:select.option value="other">Other</flux:select.option></flux:select>
        <flux:select wire:model="currency" label="Currency"><flux:select.option value="">Not set</flux:select.option>@foreach ($currencies as $code => $name)<flux:select.option :value="$code">{{ $code }} · {{ $name }}</flux:select.option>@endforeach</flux:select>
        <flux:input wire:model="provider" label="Provider (optional)" placeholder="e.g. Maybank" />
        <flux:input wire:model="accountHolderName" label="Account holder (optional)" />
        <flux:input wire:model="accountIdentifier" label="Account / wallet identifier" placeholder="Stored encrypted; only last 4 shown" />
        <div class="sm:col-span-2 flex justify-end"><flux:button type="submit" variant="primary">Add receiving account</flux:button></div>
    </form>
</section>
