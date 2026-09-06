<?php

use App\Domain\Enums\MemberRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('financial_profiles')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 40);
            $table->string('token_hash', 64)->unique();
            $table->foreignUuid('invited_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['profile_id', 'email', 'accepted_at']);
        });

        Schema::getConnection()->statement("ALTER TABLE profile_invites ADD CONSTRAINT profile_invites_role_valid CHECK (role IN ('".MemberRole::Editor->value."', '".MemberRole::Viewer->value."'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_invites');
    }
};
