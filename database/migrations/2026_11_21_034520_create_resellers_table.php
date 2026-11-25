<?php

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
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('type', 16)->default('standard');
            $table->foreignId('parent_reseller_id')->nullable()->constrained('resellers')->restrictOnDelete();
            $table->string('name', 191);
            $table->string('legal_name', 191)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('email', 255);
            $table->string('phone', 32);
            $table->string('pan', 10);
            $table->string('business_address', 500);
            $table->string('bank_proof_path', 512);
            $table->boolean('gstin_unregistered_declaration')->default(false);
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->dateTime('aup_accepted_at');
            $table->dateTime('dpdp_accepted_at');
            $table->string('kyc_status', 32)->default('minimal');
            $table->string('review_status', 32)->default('pending');
            $table->dateTime('kyc_submitted_at');
            $table->dateTime('review_sla_due_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->string('path', 1024)->default('/');
            $table->unsignedInteger('child_count')->default(0);
            $table->string('effective_status', 32)->default('active');
            $table->timestamps();
            $table->unique('tenant_id');
            $table->index(['parent_reseller_id']);
            $table->index(['review_status', 'review_sla_due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resellers');
    }
};
