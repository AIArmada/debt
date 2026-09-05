<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

test('domain schema does not use floating point columns', function () {
    $floatingPointColumns = DB::select("select m.name as table_name, p.name as column_name, p.type as column_type from sqlite_master m join pragma_table_info(m.name) p where m.type = 'table' and lower(p.type) in ('float', 'double', 'real')");

    expect($floatingPointColumns)->toBeEmpty();
});

test('money columns are integer minor units', function () {
    foreach ([
        'obligations' => ['original_amount', 'current_principal_balance', 'current_total_balance', 'minimum_payment_amount'],
        'financial_transactions' => ['amount', 'principal_amount', 'interest_amount', 'fee_amount', 'balance_before', 'balance_after'],
        'payment_schedules' => ['amount', 'per_payment_limit', 'period_limit'],
        'repayment_installments' => ['expected_amount', 'principal_amount', 'interest_amount', 'fee_amount'],
        'budget_periods' => ['emergency_reserve_amount', 'available_for_obligations_amount'],
        'cash_flow_entries' => ['amount'],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            expect(Schema::getColumnType($table, $column))->toBe('integer', "{$table}.{$column} must be stored as an integer minor unit.");
        }
    }
});

test('financial transactions have one current entry type contract', function () {
    expect(Schema::hasColumn('financial_transactions', 'entry_type'))->toBeTrue()
        ->and(Schema::hasColumn('financial_transactions', 'type'))->toBeFalse();
});
