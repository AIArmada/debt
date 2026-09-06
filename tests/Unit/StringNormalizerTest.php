<?php

use App\Domain\StringNormalizer;

test('string normalization preserves the shared input rules', function () {
    expect(StringNormalizer::trimmed('  Promise  '))->toBe('Promise')
        ->and(StringNormalizer::optionalTrimmed('  Note  '))->toBe('Note')
        ->and(StringNormalizer::optionalTrimmed(''))->toBeNull()
        ->and(StringNormalizer::uppercaseTrimmed(' myr '))->toBe('MYR')
        ->and(StringNormalizer::lowercase('Example.COM'))->toBe('example.com')
        ->and(StringNormalizer::lowercaseTrimmed(' User@Example.COM '))->toBe('user@example.com');
});
