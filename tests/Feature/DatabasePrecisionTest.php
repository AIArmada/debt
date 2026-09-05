<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

test('domain schema does not use floating point columns', function () {
    $floatingPointColumns = DB::select(
        "select columns.table_name, columns.column_name, columns.data_type as column_type
        from information_schema.columns
        join information_schema.tables
            on tables.table_schema = columns.table_schema
            and tables.table_name = columns.table_name
        where columns.table_schema = current_schema()
            and tables.table_type = 'BASE TABLE'
            and columns.data_type in ('real', 'double precision')",
    );

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
            expect(Schema::getColumnType($table, $column))->toBe('int8', "{$table}.{$column} must be stored as a PostgreSQL signed integer minor unit.");
        }
    }
});

test('financial transactions have one current entry type contract', function () {
    expect(Schema::hasColumn('financial_transactions', 'entry_type'))->toBeTrue()
        ->and(Schema::hasColumn('financial_transactions', 'type'))->toBeFalse();
});
