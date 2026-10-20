<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained('obligations')->cascadeOnDelete();
            $table->foreignUuid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->unsignedInteger('share_bps');
            $table->timestamps();
            $table->unique(['obligation_id', 'party_id']);
            $table->index(['party_id', 'obligation_id']);
        });

        DB::statement('ALTER TABLE obligation_shares ADD CONSTRAINT obligation_shares_share_bps_valid CHECK (share_bps BETWEEN 1 AND 10000)');
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_shares');
    }
};
