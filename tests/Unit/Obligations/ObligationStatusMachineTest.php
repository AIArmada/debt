<?php

use App\Domain\Obligations\ObligationStatusMachine;

test('empty balances are not settled', function () {
    expect((new ObligationStatusMachine)->isSettled([]))->toBeFalse();
});
