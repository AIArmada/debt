<?php

namespace Tests\Unit;

use App\Domain\Communication\MessageTemplates;
use App\Domain\Enums\AttachmentCategory;
use App\Domain\Enums\ClosureReason;
use App\Domain\Enums\Direction;
use App\Domain\Enums\InstalmentStatus;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRelationshipKind;
use App\Domain\Enums\PaymentDestinationKind;
use App\Domain\Enums\PlanStrategy;
use App\Domain\Enums\RecordArchiveFilter;
use App\Domain\Enums\RecordVisibility;
use App\Domain\Enums\ReminderStatus;
use App\Domain\Enums\SubjectType;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class TranslationKeyCoverageTest extends TestCase
{
    public function test_literal_translation_keys_exist_in_english_and_malay(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $keys = [];

        foreach ([$projectRoot.'/app', $projectRoot.'/resources'] as $sourceDirectory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! in_array($file->getExtension(), ['php', 'js', 'ts', 'tsx', 'vue'], true)) {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                if ($contents === false) {
                    throw new \RuntimeException('Unable to read '.$file->getPathname());
                }

                preg_match_all('/__\(\s*([\'"])([A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)+)\1/', $contents, $matches);
                array_push($keys, ...$matches[2]);
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);
        self::assertNotEmpty($keys);

        $missingKeys = [];

        foreach ($keys as $key) {
            foreach (['en', 'ms'] as $locale) {
                if (! $this->translationExists($projectRoot, $locale, $key)) {
                    $missingKeys[] = $locale.': '.$key;
                }
            }
        }

        self::assertSame([], $missingKeys, 'Missing translations: '.implode(', ', $missingKeys));

        foreach ([
            'share.acknowledgement_recorded',
            'share.dispute_recorded',
            'promises.export_zip',
        ] as $requiredKey) {
            self::assertContains($requiredKey, $keys);
        }
    }

    public function test_dynamic_translation_families_cover_enum_values_in_both_locales(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $families = [
            'promises.instalment_statuses' => array_map(static fn (InstalmentStatus $status): string => $status->value, InstalmentStatus::cases()),
            'share.instalment_statuses' => array_map(static fn (InstalmentStatus $status): string => $status->value, InstalmentStatus::cases()),
            'promises.statuses' => array_map(static fn (ObligationStatus $status): string => $status->value, ObligationStatus::cases()),
            'promises.filters' => array_map(static fn (RecordArchiveFilter $filter): string => $filter->value, RecordArchiveFilter::cases()),
            'promises.directions' => array_map(static fn (Direction $direction): string => $direction->value, Direction::cases()),
            'promises.direction_descriptions.payable' => array_map(static fn (SubjectType $subjectType): string => $subjectType->value, SubjectType::cases()),
            'promises.direction_descriptions.receivable' => array_map(static fn (SubjectType $subjectType): string => $subjectType->value, SubjectType::cases()),
            'promises.visibility_labels' => array_map(static fn (RecordVisibility $visibility): string => $visibility->value, RecordVisibility::cases()),
            'promises.closure_reasons' => array_map(static fn (ClosureReason $reason): string => $reason->value, ClosureReason::cases()),
            'promises.attachment_categories' => array_map(static fn (AttachmentCategory $category): string => $category->value, AttachmentCategory::cases()),
            'promises.reminder_statuses' => array_map(static fn (ReminderStatus $status): string => $status->value, ReminderStatus::cases()),
            'promises.subjects' => array_map(static fn (SubjectType $subjectType): string => $subjectType->value, SubjectType::cases()),
            'promises.money_entries' => array_map(static fn (MoneyEntry $entry): string => $entry->value, MoneyEntry::cases()),
            'share.money_entries' => array_map(static fn (MoneyEntry $entry): string => $entry->value, MoneyEntry::cases()),
            'app.party_kinds' => array_map(static fn (PartyKind $kind): string => $kind->value, PartyKind::cases()),
            'app.destination_kinds' => array_map(static fn (PaymentDestinationKind $kind): string => $kind->value, PaymentDestinationKind::cases()),
            'app.relationship_kinds' => array_map(static fn (PartyRelationshipKind $kind): string => $kind->value, PartyRelationshipKind::cases()),
            'plans.strategies' => array_map(static fn (PlanStrategy $strategy): string => $strategy->value, PlanStrategy::cases()),
            'templates' => MessageTemplates::KEYS,
            'templates.labels' => MessageTemplates::KEYS,
            'promises.allocation_values' => ['charges_first', 'principal_first'],
            'promises.charge_mode_values' => ['none', 'manual', 'auto_propose', 'auto_apply'],
            'promises.charge_kind_values' => ['none', 'interest', 'profit'],
            'promises.charge_basis_values' => ['flat', 'reducing_simple', 'fixed_total'],
            'promises.charge_period_values' => ['daily', 'weekly', 'monthly', 'yearly'],
            'share.charge_modes' => ['none', 'manual', 'auto_propose', 'auto_apply'],
            'share.charge_kinds' => ['none', 'interest', 'profit'],
            'share.charge_bases' => ['flat', 'reducing_simple', 'fixed_total'],
            'share.charge_periods' => ['daily', 'weekly', 'monthly', 'yearly'],
            'share.allocation_orders' => ['charges_first', 'principal_first'],
        ];

        foreach (['privacy', 'terms', 'retention', 'deletion'] as $legalPage) {
            foreach (['title', 'eyebrow', 'updated', 'intro', 'sections'] as $pageKey) {
                $families['legal.'.$legalPage][] = $pageKey;
            }
        }

        $missingKeys = [];

        foreach ($families as $family => $values) {
            foreach ($values as $value) {
                foreach (['en', 'ms'] as $locale) {
                    if (! $this->translationExists($projectRoot, $locale, $family.'.'.$value)) {
                        $missingKeys[] = $locale.': '.$family.'.'.$value;
                    }
                }
            }
        }

        self::assertSame([], $missingKeys, 'Missing dynamic translations: '.implode(', ', $missingKeys));
    }

    private function translationExists(string $projectRoot, string $locale, string $key): bool
    {
        $segments = explode('.', $key);
        $group = array_shift($segments);

        if (! is_string($group)) {
            return false;
        }

        $path = $projectRoot.'/lang/'.$locale.'/'.$group.'.php';
        if (! is_file($path)) {
            return false;
        }

        $translations = require $path;
        foreach ($segments as $segment) {
            if (! is_array($translations) || ! array_key_exists($segment, $translations)) {
                return false;
            }

            $translations = $translations[$segment];
        }

        return true;
    }
}
