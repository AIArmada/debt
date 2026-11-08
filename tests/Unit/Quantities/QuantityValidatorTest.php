<?php

use App\Features\PromiseTracker\Domain\Quantities\QuantityValidator;

test('quantity zero detection is shared by quantity input boundaries', function () {
    expect(QuantityValidator::isZero('0'))->toBeTrue()
        ->and(QuantityValidator::isZero('0.0000'))->toBeTrue()
        ->and(QuantityValidator::isZero('0.0001'))->toBeFalse();
});
