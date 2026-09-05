<?php

use App\Domain\Calculations\InstallmentCalculator;
use App\Domain\Calculations\PawnStorageCalculator;
use App\Domain\Calculations\SimpleInterestCalculator;

test('simple interest monthly charge returns minor units', function () {
    $calculator = new SimpleInterestCalculator;

    expect($calculator->monthlyCharge(100000, '10.00000000'))->toBe(833);
});

test('pawn storage fee is multiplied by periods', function () {
    $calculator = new PawnStorageCalculator;

    expect($calculator->feeForPeriods(12550, 3))->toBe(37650);
});

test('fixed installment projection is decimal safe', function () {
    $calculator = new InstallmentCalculator;

    expect($calculator->projectedTotal(20000, 6))->toBe(120000);
});
