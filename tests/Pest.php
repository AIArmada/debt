<?php

use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

pest()->tia()
    ->defaultBranch('main')
    ->directory('.pest/tia')
    ->filtered();
