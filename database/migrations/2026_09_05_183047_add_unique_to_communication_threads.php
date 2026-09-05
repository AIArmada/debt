<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Keep the earliest thread per obligation and channel, move any
            // messages from duplicates onto it, then remove the duplicates.
            // Messages must be reassigned first: their thread FK cascades on delete.
            DB::statement('WITH survivors AS (SELECT DISTINCT ON (obligation_id, channel) id, obligation_id, channel FROM communication_threads ORDER BY obligation_id, channel, created_at ASC, id ASC) UPDATE communication_messages m SET communication_thread_id = s.id FROM communication_threads t JOIN survivors s ON s.obligation_id = t.obligation_id AND s.channel = t.channel WHERE m.communication_thread_id = t.id AND t.id <> s.id');
            DB::statement('DELETE FROM communication_threads a USING communication_threads b WHERE a.ctid < b.ctid AND a.obligation_id = b.obligation_id AND a.channel = b.channel');
        }

        // One open thread per obligation and channel. Concurrent drafts are
        // serialized through insertOrIgnore plus a locked re-read.
        Schema::table('communication_threads', function (Blueprint $table): void {
            $table->dropIndex(['obligation_id']);
            $table->unique(['obligation_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('communication_threads', function (Blueprint $table): void {
            $table->dropUnique(['obligation_id', 'channel']);
            $table->index('obligation_id');
        });
    }
};
