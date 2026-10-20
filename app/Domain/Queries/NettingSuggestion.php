<?php

namespace App\Domain\Queries;

use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\User;

class NettingSuggestion
{
    public function __construct(private readonly PartyExposure $partyExposure) {}

    /**
     * @return array<string, list<array{from_party: Party, to_party: Party, amount_minor: int, currency: string}>>
     */
    public function forProfile(FinancialProfile $profile, User $viewer): array
    {
        $netByCurrency = [];
        foreach ($profile->parties()->orderBy('id')->get() as $party) {
            $exposure = $this->partyExposure->forParty($party, $viewer);
            $currencies = array_unique(array_merge(array_keys($exposure['to_pay']), array_keys($exposure['to_receive'])));
            foreach ($currencies as $currency) {
                $net = ($exposure['to_receive'][$currency] ?? 0) - ($exposure['to_pay'][$currency] ?? 0);
                if ($net !== 0) {
                    $netByCurrency[$currency][(string) $party->getKey()] = ['party' => $party, 'net' => $net];
                }
            }
        }

        $suggestions = [];
        foreach ($netByCurrency as $currency => $partyNets) {
            $debtors = array_values(array_filter($partyNets, static fn (array $entry): bool => $entry['net'] < 0));
            $creditors = array_values(array_filter($partyNets, static fn (array $entry): bool => $entry['net'] > 0));
            usort($debtors, static fn (array $left, array $right): int => (abs($right['net']) <=> abs($left['net'])) ?: strcmp((string) $left['party']->getKey(), (string) $right['party']->getKey())
            );
            usort($creditors, static fn (array $left, array $right): int => ($right['net'] <=> $left['net']) ?: strcmp((string) $left['party']->getKey(), (string) $right['party']->getKey())
            );

            $currencySuggestions = [];
            $debtorIndex = 0;
            $creditorIndex = 0;
            while (isset($debtors[$debtorIndex], $creditors[$creditorIndex])) {
                $amount = min(abs($debtors[$debtorIndex]['net']), $creditors[$creditorIndex]['net']);
                $currencySuggestions[] = [
                    'from_party' => $debtors[$debtorIndex]['party'],
                    'to_party' => $creditors[$creditorIndex]['party'],
                    'amount_minor' => $amount,
                    'currency' => $currency,
                ];
                $debtors[$debtorIndex]['net'] += $amount;
                $creditors[$creditorIndex]['net'] -= $amount;
                if ($debtors[$debtorIndex]['net'] === 0) {
                    $debtorIndex++;
                }
                if ($creditors[$creditorIndex]['net'] === 0) {
                    $creditorIndex++;
                }
            }
            $suggestions[$currency] = $currencySuggestions;
        }

        ksort($suggestions);

        return $suggestions;
    }
}
