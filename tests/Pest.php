<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature', 'Unit');

pest()->tia()
    ->directory('.pest/tia')
    ->filtered();
