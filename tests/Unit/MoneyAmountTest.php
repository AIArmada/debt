<?php

use App\Domain\Money\Decimal;
use App\Domain\Money\MoneyAmount;

test('decimal display removes only insignificant trailing zeroes', function () {
    expect(Decimal::display('1.0000'))->toBe('1')
        ->and(Decimal::display('2.5000'))->toBe('2.5')
        ->and(Decimal::display('0.1250'))->toBe('0.125')
        ->and(Decimal::display('12.34567890'))->toBe('12.3456789')
        ->and(Decimal::display('-0.0000'))->toBe('0');
});

test('major values are converted to minor units and formatted through commerce support', function () {
    expect(MoneyAmount::fromMajor('4280.00', 'MYR'))->toBe(428000)
        ->and(MoneyAmount::format(428000, 'MYR'))->toBe('RM4,280.00')
        ->and(MoneyAmount::majorInput(428000, 'MYR'))->toBe('4280.00');

    $money = MoneyAmount::money(428000, 'MYR');
    expect($money->getAmount())->toBe(428000)
        ->and($money->getCurrency()->getCurrency())->toBe('MYR');
});

test('currency precision is respected for zero decimal currencies', function () {
    expect(MoneyAmount::fromMajor('1500', 'JPY'))->toBe(1500)
        ->and(MoneyAmount::format(1500, 'JPY'))->toBe('¥1,500')
        ->and(MoneyAmount::precisionFor('JPY'))->toBe(0);
});

test('non zero fractional digits beyond currency precision are rejected', function () {
    expect(fn () => MoneyAmount::fromMajor('1.5', 'JPY'))->toThrow(InvalidArgumentException::class, 'JPY supports 0 decimal places.');
});
