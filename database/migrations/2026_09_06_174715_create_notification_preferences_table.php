<?php

use App\Domain\Enums\NotificationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 80);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'type']);
        });

        Schema::getConnection()->statement("ALTER TABLE notification_preferences ADD CONSTRAINT notification_preferences_type_valid CHECK (type IN ('".implode("','", array_column(NotificationType::cases(), 'value'))."'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
