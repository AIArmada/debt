<?php

use App\Domain\Money\Money;

test('display formats minor units without applying Money value validation', function () {
    expect(Money::display(1250, ' usd '))->toBe('$12.50')
        ->and(Money::display(-1250, 'USD'))->toBe('-$12.50');
});
