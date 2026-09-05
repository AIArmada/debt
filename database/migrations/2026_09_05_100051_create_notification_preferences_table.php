<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);
            $table->boolean('generic_push')->default(true);
            $table->unsignedTinyInteger('due_reminder_days')->default(3);
            $table->timestamps();
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
