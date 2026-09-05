<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32);
            $table->string('label')->nullable();
            $table->text('value');
            $table->string('purpose', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_message_safe')->default(false);
            $table->string('visibility', 24)->default('private');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->index(['party_id', 'type', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_contacts');
    }
};
