<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

test('every money column uses signed integer minor units', function () {
    $moneyColumns = [
        'obligations' => ['original_amount', 'current_principal_balance', 'current_total_balance', 'minimum_payment_amount', 'estimated_value'],
        'obligation_terms' => ['late_fee_amount', 'storage_fee_amount'],
        'repayment_installments' => ['expected_amount', 'principal_amount', 'interest_amount', 'fee_amount'],
        'payment_schedules' => ['amount', 'per_payment_limit', 'period_limit'],
        'financial_transactions' => ['amount', 'balance_before', 'balance_after', 'principal_amount', 'interest_amount', 'fee_amount'],
        'pledged_assets' => ['estimated_value'],
        'budget_periods' => ['emergency_reserve_amount', 'available_for_obligations_amount'],
        'cash_flow_entries' => ['amount'],
        'calculation_scenarios' => ['extra_payment'],
        'repayment_plans' => ['available_amount'],
        'repayment_plan_allocations' => ['minimum_amount', 'extra_amount', 'total_amount'],
        'payment_authorisations' => ['max_amount'],
        'payment_execution_attempts' => ['amount'],
        'bank_import_rows' => ['amount'],
    ];

    foreach ($moneyColumns as $table => $columns) {
        foreach ($columns as $column) {
            expect(['integer', 'bigint'])->toContain(Schema::getColumnType($table, $column));
        }
    }
});
