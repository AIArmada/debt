<?php

namespace App\Domain\Enums;

enum PartyRole: string
{
    case Counterparty = 'counterparty';
    case Guarantor = 'guarantor';
    case Witness = 'witness';
    case Contact = 'contact';
}
