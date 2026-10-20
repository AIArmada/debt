<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('support_audit_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('case_id')->nullable()->constrained('support_cases')->nullOnDelete();
            $table->foreignUuid('profile_id')->nullable()->constrained('financial_profiles')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['case_id', 'created_at']);
            $table->index(['profile_id', 'created_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_support_audit_entry_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $function$
            BEGIN
                RAISE EXCEPTION 'Support audit entries are immutable';
                RETURN NULL;
            END;
            $function$;
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER support_audit_entries_immutable
            BEFORE UPDATE OR DELETE ON support_audit_entries
            FOR EACH ROW
            EXECUTE FUNCTION reject_support_audit_entry_mutation();
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS support_audit_entries_immutable ON support_audit_entries');
        Schema::dropIfExists('support_audit_entries');
        DB::unprepared('DROP FUNCTION IF EXISTS reject_support_audit_entry_mutation()');
    }
};
