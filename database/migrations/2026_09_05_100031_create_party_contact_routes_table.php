<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_contact_routes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUuid('via_party_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUuid('via_contact_id')->nullable()->constrained('party_contacts')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('relationship_type', 40);
            $table->string('purpose', 40)->default('general');
            $table->unsignedSmallInteger('priority')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 30)->default('active');
            $table->text('instructions')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
            $table->unique(['party_id', 'via_party_id', 'purpose']);
            $table->index(['party_id', 'purpose', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_contact_routes');
    }
};
