<?php

namespace App\Features\PromiseTracker\Domain\Enums;

enum PartyRole: string
{
    case Counterparty = 'counterparty';
    case Guarantor = 'guarantor';
}
