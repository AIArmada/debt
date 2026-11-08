<?php

use App\Features\PromiseTracker\Domain\Obligations\ObligationStatusMachine;

test('empty balances are not settled', function () {
    expect((new ObligationStatusMachine)->isSettled([]))->toBeFalse();
});
