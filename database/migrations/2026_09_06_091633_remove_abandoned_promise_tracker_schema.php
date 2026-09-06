<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'media', 'audit_logs', 'profile_invitations', 'notification_preferences',
            'push_subscriptions', 'notifications', 'emergency_access_requests',
            'provider_webhook_events', 'payment_execution_attempts', 'payment_authorisations',
            'bank_import_rows', 'bank_imports', 'communication_messages',
            'communication_threads', 'document_links', 'documents', 'obligation_events',
            'financial_transaction_parties', 'financial_transactions', 'pledged_assets',
            'calculation_scenarios', 'exchange_rates', 'obligation_delivery_instructions',
            'party_contact_routes', 'collection_schedules', 'collection_accounts',
            'obligation_payment_instructions', 'party_payment_destinations', 'obligation_parties',
            'party_relationships', 'party_addresses', 'party_contacts', 'integrations',
            'repayment_plan_allocations', 'repayment_plans', 'cash_flow_entries',
            'budget_periods', 'payment_schedules', 'repayment_installments', 'obligation_terms',
        ] as $table) {
            DB::statement('DROP TABLE IF EXISTS "'.$table.'" CASCADE');
        }

        $this->removeColumns('financial_profiles', [
            'type', 'locale', 'is_islamic_mode_enabled',
        ]);

        $this->removeColumns('profile_members', ['permissions']);

        if (Schema::hasTable('records')) {
            if (Schema::hasColumn('records', 'description') && ! Schema::hasColumn('records', 'note')) {
                Schema::table('records', function (Blueprint $table): void {
                    $table->renameColumn('description', 'note');
                });
            } else {
                $this->removeColumns('records', ['description']);
            }

            $this->removeColumns('records', ['sensitivity']);
        }

        if (Schema::hasTable('parties')) {
            if (Schema::hasColumn('parties', 'preferred_name') && ! Schema::hasColumn('parties', 'display_name')) {
                Schema::table('parties', function (Blueprint $table): void {
                    $table->renameColumn('preferred_name', 'display_name');
                });
            } else {
                $this->removeColumns('parties', ['preferred_name']);
            }

            $this->removeColumns('parties', [
                'created_by_user_id', 'legal_name', 'aliases', 'identifiers',
                'verification_status', 'source', 'archived_at',
            ]);
        }

        $this->removeColumns('record_parties', [
            'created_by_user_id', 'responsibility_scope', 'status', 'notes',
            'valid_from', 'valid_to', 'visibility',
        ]);

        if (Schema::hasTable('obligations')) {
            if (Schema::hasColumn('obligations', 'status')) {
                DB::table('obligations')->where('status', 'active')->update(['status' => 'open']);
                DB::table('obligations')
                    ->whereNotIn('status', ['open', 'settled', 'waived'])
                    ->update(['status' => 'open']);
            }

            if (! Schema::hasColumn('obligations', 'subject_type')) {
                Schema::table('obligations', function (Blueprint $table): void {
                    $table->string('subject_type', 20)->default('money');
                });
            }

            $this->removeColumns('obligations', [
                'obligation_kind', 'category', 'description', 'tracking_mode', 'currency',
                'original_amount', 'current_principal_balance', 'current_total_balance',
                'currency_opening_balances', 'currency_balances', 'minimum_payment_amount',
                'started_on', 'next_due_on', 'data_confidence', 'is_interest_bearing',
                'settled_at', 'subject_name', 'subject_quantity', 'current_subject_quantity',
                'quantity_mode', 'subject_unit', 'subject_condition', 'subject_details',
                'asset_type', 'service_type', 'estimated_value', 'estimated_value_currency',
                'completion_criteria', 'is_conditional', 'condition_description',
                'condition_triggered_on',
            ]);

            DB::statement("ALTER TABLE obligations ALTER COLUMN status SET DEFAULT 'open'");
        }

        if (Schema::hasTable('activity_entries') && Schema::hasColumn('activity_entries', 'actor_user_id')) {
            Schema::table('activity_entries', function (Blueprint $table): void {
                $table->dropForeign(['actor_user_id']);
                $table->foreignUuid('actor_user_id')->nullable()->change();
                $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // The legacy schema is intentionally not restored.
    }

    /**
     * @param  list<string>  $columns
     */
    private function removeColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($tableName, $column),
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }
};
