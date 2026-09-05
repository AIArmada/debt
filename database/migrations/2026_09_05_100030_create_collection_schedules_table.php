<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_schedules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('collection_account_id')->nullable()->constrained('collection_accounts')->nullOnDelete();
            $table->string('mode', 30)->default('manual_follow_up');
            $table->string('status', 30)->default('active');
            $table->bigInteger('amount');
            $table->string('currency', 3);
            $table->string('frequency', 30);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->string('collection_method', 30)->default('bank_transfer');
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->text('note')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
            $table->index(['obligation_id', 'status', 'next_due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_schedules');
    }
};
