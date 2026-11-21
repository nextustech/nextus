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
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('email');
            $table->json('roles');
            $table->json('department_grants')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('status', 32)->default('pending');
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['tenant_id', 'email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->string('status', 32)->default('active')->after('password');
            $table->string('phone', 32)->nullable()->after('status');
            $table->char('locale', 5)->default('en-IN')->after('phone');
            $table->string('timezone', 64)->default('Asia/Kolkata')->after('locale');
            $table->dateTime('last_login_at')->nullable()->after('timezone');
            $table->softDeletes();
            $table->unique(['email', 'tenant_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email', 'tenant_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn(['status', 'phone', 'locale', 'timezone', 'last_login_at']);
            $table->unique('email');
        });
        Schema::dropIfExists('user_invitations');
    }
};
