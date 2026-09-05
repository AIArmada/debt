<?php

namespace App\Domain\Obligations;

enum ObligationKind: string
{
    case Money = 'money';
    case Asset = 'asset';
    case Service = 'service';
    case Action = 'action';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Money => 'Money',
            self::Asset => 'Asset / item',
            self::Service => 'Service / time',
            self::Action => 'Action / commitment',
        };
    }

    /** @return array<string, string> */
    public function categoryOptions(): array
    {
        return match ($this) {
            self::Money => [
                'personal_loan' => 'Personal loan / cash advance',
                'family_support' => 'Family / household support',
                'bank_financing' => 'Bank loan / financing',
                'credit_card' => 'Credit card',
                'housing' => 'Housing / mortgage',
                'bill' => 'Bill / utilities',
                'tax' => 'Tax / government',
                'business_debt' => 'Business / trade',
                'supplier' => 'Supplier / invoice',
                'pawn_loan' => 'Pawn / Ar-Rahnu financing',
                'instalment' => 'Purchase / instalment',
                'other_money' => 'Other money obligation',
            ],
            self::Asset => [
                'borrowed_item' => 'Borrowed personal item',
                'precious_item' => 'Gold / jewellery / precious item',
                'vehicle' => 'Vehicle / transport asset',
                'equipment' => 'Equipment / electronics',
                'document' => 'Document / identification',
                'digital_asset' => 'Digital item / data / account',
                'property' => 'Property / premises',
                'inventory' => 'Inventory / goods',
                'pawned_asset' => 'Pawned / pledged asset',
                'other_asset' => 'Other asset or item',
            ],
            self::Service => [
                'personal_help' => 'Personal help / household work',
                'professional_service' => 'Professional service',
                'repair_maintenance' => 'Repair / maintenance',
                'caregiving' => 'Caregiving / responsibility',
                'transport_delivery' => 'Transport / delivery',
                'consulting_advice' => 'Consulting / advice',
                'education_training' => 'Education / training',
                'creative_work' => 'Creative / production work',
                'other_service' => 'Other service or time',
            ],
            self::Action => [
                'promise' => 'Promise / undertaking',
                'document_filing' => 'Document / administration',
                'delivery_handover' => 'Delivery / handover',
                'introduction' => 'Introduction / referral',
                'confidentiality' => 'Confidentiality / non-disclosure',
                'legal_compliance' => 'Legal / compliance action',
                'estate_family' => 'Estate / family arrangement',
                'repair_action' => 'Repair / corrective action',
                'other_action' => 'Other promise or commitment',
            ],
        };
    }

    public function defaultCategory(): string
    {
        return array_key_first($this->categoryOptions());
    }

    public function categoryFieldLabel(): string
    {
        return match ($this) {
            self::Money => 'Money category',
            self::Asset => 'Asset category',
            self::Service => 'Service category',
            self::Action => 'Commitment category',
        };
    }

    public function categoryHelpText(): string
    {
        return match ($this) {
            self::Money => 'Choose the financial context. This controls how the obligation is grouped and reviewed.',
            self::Asset => 'Choose what kind of thing is being borrowed, held, returned, replaced, or pledged.',
            self::Service => 'Choose the kind of work, care, time, or practical help that is owed.',
            self::Action => 'Choose the kind of promise, deliverable, or responsibility that must be completed.',
        };
    }

    public function isMoney(): bool
    {
        return $this === self::Money;
    }

    public function isQuantityBased(): bool
    {
        return in_array($this, [self::Asset, self::Service], true);
    }
}
