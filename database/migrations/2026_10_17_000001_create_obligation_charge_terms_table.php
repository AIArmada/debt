<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obligation_charge_terms', function (Blueprint $table): void {
            $table->foreignUuid('obligation_id')->primary()->constrained('obligations')->cascadeOnDelete();
            $table->string('mode', 30)->default('none');
            $table->string('kind', 30)->default('none');
            $table->decimal('rate', 12, 8)->nullable();
            $table->string('basis', 30)->nullable();
            $table->string('period', 30)->nullable();
            $table->unsignedInteger('grace_days')->default(0);
            $table->unsignedBigInteger('cap_minor')->nullable();
            $table->unsignedBigInteger('total_payable_minor')->nullable();
            $table->string('penalty_kind', 30)->default('none');
            $table->unsignedBigInteger('penalty_value')->nullable();
            $table->string('allocation_order', 30)->default('charges_first');
            $table->boolean('is_formal')->default(false);
            $table->boolean('auto_apply_on_acceptance')->default(false);
            $table->date('next_charge_on')->nullable();
            $table->date('last_charge_on')->nullable();
            $table->char('terms_hash', 64)->nullable();
            $table->char('accepted_terms_hash', 64)->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_name')->nullable();
            $table->timestamp('fixed_total_agreed_upfront_at')->nullable();
            $table->foreignUuid('fixed_total_agreed_upfront_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fixed_total_posted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_charge_terms');
    }
};
