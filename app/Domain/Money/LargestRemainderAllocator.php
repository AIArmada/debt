<?php

namespace App\Domain\Money;

final class LargestRemainderAllocator
{
    /**
     * @param  list<array{party_id: string, share_bps: int}>  $shares
     * @return array<string, int>
     */
    public function allocate(int $amountMinor, array $shares): array
    {
        if ($shares === []) {
            return [];
        }

        $totalShareBps = array_sum(array_column($shares, 'share_bps'));
        if ($totalShareBps !== 10000) {
            throw new \InvalidArgumentException('Shares must total exactly 10000 basis points.');
        }

        $amountMinor = abs($amountMinor);
        $wholeUnits = intdiv($amountMinor, 10000);
        $fractionalMinor = $amountMinor % 10000;
        $allocations = [];
        $remainders = [];
        $allocated = 0;

        foreach ($shares as $share) {
            $fractionalNumerator = $fractionalMinor * $share['share_bps'];
            $amount = ($wholeUnits * $share['share_bps']) + intdiv($fractionalNumerator, 10000);
            $allocations[$share['party_id']] = $amount;
            $remainders[] = [
                'party_id' => $share['party_id'],
                'remainder' => $fractionalNumerator % 10000,
            ];
            $allocated += $amount;
        }

        usort($remainders, static fn (array $left, array $right): int => ($right['remainder'] <=> $left['remainder']) ?: strcmp($left['party_id'], $right['party_id'])
        );

        $remaining = $amountMinor - $allocated;
        for ($index = 0; $index < $remaining; $index++) {
            $partyId = $remainders[$index]['party_id'];
            $allocations[$partyId]++;
        }

        return $allocations;
    }
}
