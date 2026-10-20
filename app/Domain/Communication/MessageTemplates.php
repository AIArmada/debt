<?php

namespace App\Domain\Communication;

use App\Domain\Enums\PartyRole;
use App\Domain\Money\Money;
use App\Domain\Queries\OutstandingBalance;
use App\Models\Obligation;
use App\Models\Record;
use Illuminate\Support\Carbon;

final class MessageTemplates
{
    /** @var list<string> */
    public const KEYS = [
        'gentle_reminder',
        'firm_reminder',
        'receipt_thanks',
        'hardship_request',
        'forgiveness_notice',
        'share_invite',
    ];

    public function __construct(private readonly OutstandingBalance $outstandingBalance) {}

    public function renderFor(
        string $locale,
        string $template,
        Record $record,
        Obligation $obligation,
        ?string $shareUrl = null,
        ?string $destination = null,
    ): string {
        $locale = in_array($locale, ['en', 'ms'], true) ? $locale : 'en';
        if (! in_array($template, self::KEYS, true)) {
            $template = 'gentle_reminder';
        }

        $obligation->loadMissing(['moneySubject', 'instalments']);
        $party = $record->partyLinks()
            ->where('role', PartyRole::Counterparty->value)
            ->where('is_primary', true)
            ->with('party')
            ->first()?->party;
        $currency = (string) ($obligation->moneySubject->currency ?? $record->profile->base_currency);
        $balance = $this->outstandingBalance->forObligation($obligation)[$currency] ?? 0;
        $nextDue = $obligation->instalments
            ->first(fn ($instalment): bool => $instalment->paid_minor < $instalment->amount_minor)?->due_on;
        $dueDate = $nextDue ?? $obligation->due_on;
        if ($dueDate === null) {
            $formattedDue = (string) __('templates.no_due_date', [], $locale);
        } else {
            $date = Carbon::parse($dueDate);
            $date->locale($locale);
            $formattedDue = $date->translatedFormat('d M Y');
        }
        $destination ??= (string) ($party === null ? '' : ($party->paymentDestinations()->orderBy('label')->value('label') ?? ''));

        $message = trim((string) __('templates.'.$template, [
            'name' => $party->display_name ?? $record->title,
            'purpose' => $record->title,
            'amount' => Money::display(abs($balance), $currency),
            'due' => $formattedDue,
            'share_url' => $shareUrl ?? '',
            'destination' => $destination,
        ], $locale));

        if ($destination === '') {
            $message = preg_replace('/\s+[^.!?]+:\s*$/u', '', $message) ?? $message;
        }

        if ($shareUrl !== null && $shareUrl !== '' && ! str_contains($message, $shareUrl)) {
            $message .= ' '.$shareUrl;
        }

        return $message;
    }
}
