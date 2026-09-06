<?php

use App\Domain\Enums\ReminderChannel;
use App\Domain\Enums\ReminderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('obligation_id')->constrained('obligations')->cascadeOnDelete();
            $table->date('remind_on');
            $table->date('snoozed_until')->nullable();
            $table->string('channel', 30)->default(ReminderChannel::Database->value);
            $table->string('status', 30)->default(ReminderStatus::Pending->value);
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['obligation_id', 'status']);
        });

        Schema::getConnection()->statement("ALTER TABLE reminders ADD CONSTRAINT reminders_channel_valid CHECK (channel IN ('".ReminderChannel::Database->value."'))");
        Schema::getConnection()->statement("ALTER TABLE reminders ADD CONSTRAINT reminders_status_valid CHECK (status IN ('".implode("','", array_column(ReminderStatus::cases(), 'value'))."'))");

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuid('notifiable_id');
            $table->string('notifiable_type');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reminders');
    }
};
