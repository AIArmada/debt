<?php

use App\Domain\Charges\ChargeCalculator;
use App\Models\ObligationChargeTerm;
use Carbon\CarbonImmutable;

test('flat monthly rate uses the original principal and rounds to minor units', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01500000',
        'period' => 'monthly',
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-02-01'),
        100000,
        100000,
        0,
    );

    expect($result['amount_minor'])->toBe(1500)
        ->and($result['breakdown']['basis_amount_minor'])->toBe(100000)
        ->and($result['breakdown']['whole_periods'])->toBe(1);
});

test('reducing simple daily rate uses the outstanding principal portion', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'reducing_simple',
        'rate' => '0.01500000',
        'period' => 'daily',
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-01-11'),
        100000,
        60000,
        0,
    );

    expect($result['amount_minor'])->toBe(9000)
        ->and($result['breakdown']['basis_amount_minor'])->toBe(60000)
        ->and($result['breakdown']['days'])->toBe(10)
        ->and($result['breakdown']['whole_periods'])->toBe(10);
});

test('a reversed calculation window produces no charge', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01500000',
        'period' => 'monthly',
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-02-01'),
        CarbonImmutable::parse('2026-01-01'),
        100000,
        100000,
        0,
    );

    expect($result['amount_minor'])->toBe(0)
        ->and($result['breakdown']['days'])->toBe(0)
        ->and($result['breakdown']['whole_periods'])->toBe(0);
});

test('lifetime cap subtracts confirmed charges already posted', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01500000',
        'period' => 'monthly',
        'cap_minor' => 2000,
    ]);
    $calculator = app(ChargeCalculator::class);

    $remaining = $calculator->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-02-01'),
        100000,
        100000,
        1800,
    );
    $exhausted = $calculator->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-02-01'),
        100000,
        100000,
        2200,
    );

    expect($remaining['amount_minor'])->toBe(200)
        ->and($remaining['breakdown']['cap_applied'])->toBeTrue()
        ->and($exhausted['amount_minor'])->toBe(0);
});

test('lifetime cap reserves proposed charges before they are confirmed', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01500000',
        'period' => 'monthly',
        'cap_minor' => 2000,
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-02-01'),
        100000,
        100000,
        500,
        1300,
    );

    expect($result['amount_minor'])->toBe(200)
        ->and($result['breakdown']['already_charged_confirmed_minor'])->toBe(500)
        ->and($result['breakdown']['reserved_proposed_minor'])->toBe(1300)
        ->and($result['breakdown']['cap_remaining_before_minor'])->toBe(200);
});

test('monthly anniversaries clamp January 31 to February month end', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01500000',
        'period' => 'monthly',
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-31'),
        CarbonImmutable::parse('2026-02-28'),
        100000,
        100000,
        0,
    );

    expect($result['amount_minor'])->toBe(1500)
        ->and($result['breakdown']['whole_periods'])->toBe(1)
        ->and($result['breakdown']['remainder_days'])->toBe(0);
});

test('monthly charge factors return to the original January 31 anniversary', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01500000',
        'period' => 'monthly',
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2026-01-31'),
        CarbonImmutable::parse('2026-03-31'),
        100000,
        100000,
        0,
    );

    expect($result['amount_minor'])->toBe(3000)
        ->and($result['breakdown']['whole_periods'])->toBe(2)
        ->and($result['breakdown']['remainder_days'])->toBe(0);
});

test('yearly charge factors return to the original leap day anniversary', function () {
    $terms = new ObligationChargeTerm([
        'basis' => 'flat',
        'rate' => '0.01000000',
        'period' => 'yearly',
    ]);

    $result = app(ChargeCalculator::class)->calculate(
        $terms,
        'MYR',
        CarbonImmutable::parse('2024-02-29'),
        CarbonImmutable::parse('2028-02-29'),
        100000,
        100000,
        0,
    );

    expect($result['amount_minor'])->toBe(4000)
        ->and($result['breakdown']['whole_periods'])->toBe(4)
        ->and($result['breakdown']['remainder_days'])->toBe(0);
});
