<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 20);
            $table->string('status', 30)->default('draft');
            $table->string('recipient')->nullable();
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_messages');
    }
};
