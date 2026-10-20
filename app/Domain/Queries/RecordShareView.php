<?php

namespace App\Domain\Queries;

use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\SubjectType;
use App\Models\Obligation;
use App\Models\RecordShare;
use Illuminate\Database\Eloquent\Relations\Relation;

final class RecordShareView
{
    public function __construct(
        private readonly MoneyBalanceQuery $moneyBalanceQuery,
        private readonly BalanceBreakdown $balanceBreakdown,
        private readonly ChargeTermsHash $chargeTermsHash,
    ) {}

    /** @return array<string, mixed> */
    public function forShare(RecordShare $share): array
    {
        $share->loadMissing('record');
        $record = $share->record;
        $record->load([
            'attachments',
            'partyLinks.party',
            'obligations.moneySubject',
            'obligations.chargeTerms',
            'obligations.instalments',
            'obligations.attachments',
            'obligations.moneyMovements' => function (Relation $query): void {
                $query->where('status', MovementStatus::Confirmed->value)->with('attachments');
            },
            'obligations.quantityReturns' => function (Relation $query): void {
                $query->where('status', MovementStatus::Confirmed->value)->with('attachments');
            },
        ]);

        $terms = [];
        $termsHashes = [];
        $balances = [];
        $breakdowns = [];
        $instalments = [];
        $movements = [];
        $returns = [];
        $evidenceNames = $record->attachments->pluck('original_name')->all();
        $breakdownsByObligation = $this->balanceBreakdown->forObligations($record->obligations);

        foreach ($record->obligations as $obligation) {
            $evidenceNames = array_merge($evidenceNames, $obligation->attachments->pluck('original_name')->all());
            if ($obligation->subject_type === SubjectType::Money) {
                foreach ($breakdownsByObligation[(string) $obligation->getKey()] ?? [] as $currency => $breakdown) {
                    $breakdowns[] = [
                        'obligation_title' => $obligation->title,
                        'currency' => (string) $currency,
                        'borrowed_minor' => (int) $breakdown['principal_in'],
                        'charges_minor' => (int) $breakdown['charges_in'],
                        'paid_minor' => (int) $breakdown['settled_total'],
                        'unpaid_principal_minor' => (int) $breakdown['unpaid_principal'],
                        'unpaid_charges_minor' => (int) $breakdown['unpaid_charges'],
                    ];
                }

                foreach ($obligation->instalments as $instalment) {
                    $instalments[] = [
                        'sequence' => (int) $instalment->sequence,
                        'due_on' => $instalment->due_on->format('d M Y'),
                        'amount_minor' => (int) $instalment->amount_minor,
                        'paid_minor' => (int) $instalment->paid_minor,
                        'currency' => $instalment->currency,
                        'status' => $instalment->status()->value,
                    ];
                }
            }

            if ($obligation->chargeTerms !== null && $obligation->moneySubject !== null) {
                $hash = $this->chargeTermsHash->forObligation($obligation);
                $termsHashes[$obligation->getKey()] = $hash;
                $terms[] = [
                    'obligation_title' => $obligation->title,
                    'mode' => $obligation->chargeTerms->mode,
                    'kind' => $obligation->chargeTerms->kind,
                    'rate' => $obligation->chargeTerms->rate,
                    'basis' => $obligation->chargeTerms->basis,
                    'period' => $obligation->chargeTerms->period,
                    'grace_days' => $obligation->chargeTerms->grace_days,
                    'cap_minor' => $obligation->chargeTerms->cap_minor,
                    'total_payable_minor' => $obligation->chargeTerms->total_payable_minor,
                    'penalty_kind' => $obligation->chargeTerms->penalty_kind,
                    'penalty_value' => $obligation->chargeTerms->penalty_value,
                    'allocation_order' => $obligation->chargeTerms->allocation_order,
                    'is_formal' => $obligation->chargeTerms->is_formal,
                    'auto_apply_on_acceptance' => $obligation->chargeTerms->auto_apply_on_acceptance,
                    'fixed_total_agreed_upfront_at' => $obligation->chargeTerms->fixed_total_agreed_upfront_at,
                    'currency' => $obligation->moneySubject->currency,
                ];
                foreach ($this->moneyBalanceQuery->forObligation($obligation) as $currency => $amount) {
                    if ($amount !== 0) {
                        $balances[] = [
                            'obligation_title' => $obligation->title,
                            'currency' => $currency,
                            'amount_minor' => abs($amount),
                            'direction' => $obligation->direction->bucketForBalance($amount) === 'to_pay' ? __('promises.directions.payable') : __('promises.directions.receivable'),
                        ];
                    }
                }
            } elseif ($obligation->subject_type === SubjectType::Money) {
                foreach ($this->moneyBalanceQuery->forObligation($obligation) as $currency => $amount) {
                    if ($amount !== 0) {
                        $balances[] = [
                            'obligation_title' => $obligation->title,
                            'currency' => $currency,
                            'amount_minor' => abs($amount),
                            'direction' => $obligation->direction->bucketForBalance($amount) === 'to_pay' ? __('promises.directions.payable') : __('promises.directions.receivable'),
                        ];
                    }
                }
            }

            foreach ($obligation->moneyMovements as $movement) {
                $movements[] = [
                    'title' => $obligation->title,
                    'entry' => $movement->entry->value,
                    'amount_minor' => $movement->amount_minor,
                    'currency' => $movement->currency,
                    'occurred_on' => $movement->occurred_on->format('d M Y'),
                ];
                $evidenceNames = array_merge($evidenceNames, $movement->attachments->pluck('original_name')->all());
            }

            foreach ($obligation->quantityReturns as $return) {
                $returns[] = [
                    'title' => $obligation->title,
                    'quantity' => $return->quantity,
                    'occurred_on' => $return->occurred_on->format('d M Y'),
                ];
                $evidenceNames = array_merge($evidenceNames, $return->attachments->pluck('original_name')->all());
            }
        }

        $allTermsAccepted = $termsHashes !== [];
        foreach ($record->obligations as $obligation) {
            if (isset($termsHashes[$obligation->getKey()])
                && $obligation->chargeTerms?->accepted_terms_hash !== $termsHashes[$obligation->getKey()]) {
                $allTermsAccepted = false;
            }
        }

        return [
            'share' => [
                'disputed_at' => $share->disputed_at,
                'disputed_name' => $share->disputed_name,
                'acknowledged_at' => $share->acknowledged_at,
                'acknowledged_name' => $share->acknowledged_name,
            ],
            'record' => [
                'title' => $record->title,
                'obligations' => $record->obligations->map(static fn (Obligation $obligation): array => [
                    'direction' => __('promises.directions.'.$obligation->direction->value),
                    'status' => __('promises.statuses.'.$obligation->status->value),
                ])->values()->all(),
            ],
            'guarantors' => $record->partyLinks
                ->where('role', PartyRole::Guarantor)
                ->map(static fn ($link): ?string => $link->party?->display_name)
                ->filter()
                ->values()
                ->all(),
            'terms' => $terms,
            'terms_hashes' => $termsHashes,
            'has_terms' => $terms !== [],
            'all_terms_accepted' => $allTermsAccepted,
            'balances' => $balances,
            'breakdowns' => $breakdowns,
            'instalments' => $instalments,
            'movements' => $movements,
            'returns' => $returns,
            'evidence_names' => array_values(array_unique($evidenceNames)),
            'show_acknowledge_form' => $share->disputed_at === null && ($terms === [] ? $share->acknowledged_at === null : ! $allTermsAccepted),
            'show_dispute_form' => $share->disputed_at === null,
        ];
    }
}
