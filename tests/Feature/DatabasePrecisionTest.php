<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

test('promise schema does not use floating point columns', function () {
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

test('money is stored as integer minor units and quantities use fixed precision', function () {
    expect(Schema::getColumnType('money_movements', 'amount_minor'))->toBe('int8')
        ->and(Schema::getColumnType('quantity_subjects', 'total'))->toBe('numeric')
        ->and(Schema::hasColumn('quantity_subjects', 'returned'))->toBeFalse()
        ->and(Schema::getColumnType('quantity_returns', 'quantity'))->toBe('numeric');
});

test('the schema contains only the from-scratch promise surface', function () {
    foreach ([
        'obligation_terms',
        'payment_schedules',
        'financial_transactions',
        'documents',
        'integrations',
    ] as $legacyTable) {
        expect(Schema::hasTable($legacyTable))->toBeFalse($legacyTable.' must not exist.');
    }

    foreach ([
        ['financial_profiles', 'type'],
        ['records', 'description'],
        ['records', 'sensitivity'],
        ['profile_members', 'permissions'],
        ['record_parties', 'status'],
        ['obligations', 'tracking_mode'],
        ['obligations', 'current_total_balance'],
        ['obligations', 'currency_balances'],
    ] as [$table, $column]) {
        expect(Schema::hasColumn($table, $column))->toBeFalse($table.'.'.$column.' must not exist.');
    }
});
