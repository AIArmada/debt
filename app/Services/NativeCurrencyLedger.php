<?php

namespace App\Services;

use App\Models\FinancialTransaction;
use App\Models\Obligation;
use Illuminate\Database\Eloquent\Collection;

class NativeCurrencyLedger
{
    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return array<string, int>
     */
    public function openingBalances(Obligation $obligation, Collection $transactions): array
    {
        $stored = $this->normaliseMap($obligation->getAttribute('currency_opening_balances'));
        $primary = strtoupper((string) $obligation->currency);

        if ($stored !== []) {
            $stored[$primary] ??= (int) ($obligation->current_total_balance ?? 0);

            return $stored;
        }

        $confirmed = $transactions
            ->where('status', 'confirmed')
            ->sortBy(fn (FinancialTransaction $transaction): string => $this->sortKey($transaction))
            ->values();
        $first = $confirmed->first();
        $balance = null;

        if ($first instanceof FinancialTransaction && $first->balance_before !== null) {
            $balance = (int) $first->balance_before;
        } elseif ($first instanceof FinancialTransaction && $first->balance_after !== null) {
            $amount = (int) $first->amount;
            $after = (int) $first->balance_after;
            $balance = $first->balance_effect === 'increase'
                ? $after - $amount
                : $after + $amount;
        }

        return [$primary => $balance ?? (int) ($obligation->current_total_balance ?? 0)];
    }

    /** @return array<string, int> */
    public function currentBalances(Obligation $obligation): array
    {
        $balances = $this->normaliseMap($obligation->getAttribute('currency_balances'));
        $primary = strtoupper((string) $obligation->currency);

        if (! array_key_exists($primary, $balances)) {
            $balances[$primary] = (int) ($obligation->current_total_balance ?? 0);
        }

        return $balances;
    }

    /** @return array{before: int, after: int} */
    public function record(Obligation $obligation, int $amount, string $currency, string $entryType, string $balanceEffect): array
    {
        $currency = strtoupper($currency);
        $opening = $this->openingBalances($obligation, new Collection);
        $balances = $this->currentBalances($obligation);
        $opening[$currency] ??= 0;
        $balances[$currency] ??= $opening[$currency];

        $before = $balances[$currency];
        $after = $balanceEffect === 'increase' ? $before + $amount : $before - $amount;
        $balances[$currency] = $after;

        $primary = strtoupper((string) $obligation->currency);
        $attributes = [
            'currency_opening_balances' => $opening,
            'currency_balances' => $balances,
        ];

        if ($currency === $primary) {
            $attributes['current_total_balance'] = $after;
            $this->updatePrincipal($obligation, $attributes, $amount, $entryType, $balanceEffect);
        }

        $this->applyStatus($obligation, $attributes, $balances);
        $obligation->forceFill($attributes)->save();

        return ['before' => $before, 'after' => $after];
    }

    /**
     * @param  Collection<int, FinancialTransaction>  $transactions
     * @return list<string>
     */
    public function recalculate(Obligation $obligation, Collection $transactions, ?int $openingPrincipal = null): array
    {
        $opening = $this->openingBalances($obligation, $transactions);
        $balances = $opening;
        $primary = strtoupper((string) $obligation->currency);
        $principal = $openingPrincipal ?? $this->openingPrincipal($obligation, $transactions);
        $recalculated = [];

        $ordered = $transactions->sortBy(fn (FinancialTransaction $transaction): string => $this->sortKey($transaction));

        foreach ($ordered as $transaction) {
            if ($transaction->status !== 'confirmed') {
                $transaction->forceFill(['balance_before' => null, 'balance_after' => null])->saveQuietly();

                continue;
            }

            $currency = strtoupper((string) $transaction->currency);
            $balances[$currency] ??= 0;
            $amount = (int) $transaction->amount;
            $effect = $transaction->balance_effect;
            $before = $balances[$currency];
            $after = $effect === 'increase' ? $before + $amount : $before - $amount;
            $balances[$currency] = $after;

            $entryType = $transaction->entry_type;
            if ($currency === $primary && $principal !== null && in_array($entryType, ['payment', 'collection', 'advance', 'write_off'], true)) {
                if ($effect === 'increase' && $entryType === 'advance') {
                    $principal += $amount;
                } else {
                    $principal -= min($amount, max(0, $principal));
                }
            }

            $transaction->forceFill(['balance_before' => $before, 'balance_after' => $after])->saveQuietly();
            $recalculated[] = (string) $transaction->getKey();
        }

        $attributes = [
            'currency_opening_balances' => $opening,
            'currency_balances' => $balances,
            'current_total_balance' => $balances[$primary] ?? 0,
            'current_principal_balance' => $principal,
        ];
        $this->applyStatus($obligation, $attributes, $balances);
        $obligation->forceFill($attributes)->save();

        return $recalculated;
    }

    /** @param array<string, mixed> $attributes */
    private function updatePrincipal(Obligation $obligation, array &$attributes, int $amount, string $entryType, string $balanceEffect): void
    {
        $principal = $obligation->current_principal_balance;

        if ($principal === null || ! in_array($entryType, ['payment', 'collection', 'advance', 'write_off'], true)) {
            return;
        }

        $attributes['current_principal_balance'] = $balanceEffect === 'increase' && $entryType === 'advance'
            ? $principal + $amount
            : $principal - min($amount, max(0, $principal));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, int>  $balances
     */
    private function applyStatus(Obligation $obligation, array &$attributes, array $balances): void
    {
        $settled = collect($balances)->every(fn (mixed $balance): bool => (int) $balance === 0);
        $attributes['status'] = $settled ? 'settled' : 'active';
        $attributes['settled_at'] = $settled ? now() : null;
    }

    /** @param Collection<int, FinancialTransaction> $transactions */
    public function openingPrincipal(Obligation $obligation, Collection $transactions): ?int
    {
        $principal = $obligation->current_principal_balance;

        if ($principal === null) {
            return null;
        }

        $primary = strtoupper((string) $obligation->currency);
        $confirmed = $transactions
            ->where('status', 'confirmed')
            ->sortBy(fn (FinancialTransaction $transaction): string => $this->sortKey($transaction))
            ->values();

        foreach ($confirmed->reverse() as $transaction) {
            if (strtoupper((string) $transaction->currency) !== $primary) {
                continue;
            }

            $entryType = $transaction->entry_type;
            if (! in_array($entryType, ['payment', 'collection', 'advance', 'write_off'], true)) {
                continue;
            }

            $amount = (int) $transaction->amount;
            $effect = $transaction->balance_effect;
            $principal = $effect === 'increase' && $entryType === 'advance'
                ? $principal - $amount
                : $principal + $amount;
        }

        return $principal;
    }

    /** @return array<string, int> */
    private function normaliseMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalised = [];
        foreach ($value as $currency => $amount) {
            if (is_string($currency) && (is_int($amount) || (is_string($amount) && preg_match('/^-?\d+$/D', $amount) === 1))) {
                $normalised[strtoupper($currency)] = (int) $amount;
            }
        }

        return $normalised;
    }

    private function sortKey(FinancialTransaction $transaction): string
    {
        return sprintf('%s|%s|%s', $transaction->occurred_on?->format('Y-m-d') ?? '9999-12-31', $transaction->created_at?->format('Y-m-d H:i:s.u') ?? '', $transaction->getKey());
    }
}
