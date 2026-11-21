<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parent-created sub-resellers carry only the minimum business details at
     * invite time; PAN / bank proof / policy acceptances are completed by the
     * child owner after provisioning (KYC status "minimal" until then).
     */
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('owner_name', 100)->nullable()->after('legal_name');
            $table->string('city', 100)->nullable()->after('business_address');
            $table->text('invite_note')->nullable()->after('city');
            $table->foreignId('invited_by')->nullable()->after('reviewer_id')->constrained('users')->nullOnDelete();
            $table->dateTime('invited_at')->nullable()->after('invited_by');
            $table->dateTime('activated_at')->nullable()->after('invited_at');

            $table->string('phone', 32)->nullable()->change();
            $table->string('pan', 10)->nullable()->change();
            $table->string('business_address', 500)->nullable()->change();
            $table->string('bank_proof_path', 512)->nullable()->change();
            $table->dateTime('aup_accepted_at')->nullable()->change();
            $table->dateTime('dpdp_accepted_at')->nullable()->change();
            $table->dateTime('kyc_submitted_at')->nullable()->change();
            $table->dateTime('review_sla_due_at')->nullable()->change();
        });

        Schema::table('user_invitations', function (Blueprint $table) {
            $table->string('type', 32)->default('staff')->after('id');
            $table->foreignId('sub_reseller_id')->nullable()->after('tenant_id')->constrained('resellers')->nullOnDelete();
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropIndex(['type', 'status']);
            $table->dropForeign(['sub_reseller_id']);
            $table->dropColumn(['sub_reseller_id', 'type']);
        });

        Schema::table('resellers', function (Blueprint $table) {
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['owner_name', 'city', 'invite_note', 'invited_by', 'invited_at', 'activated_at']);

            // KYC columns stay nullable on rollback: parent-created children
            // are intentionally inserted without full KYC, and restoring
            // NOT NULL here would make rollback fail once invitations exist.
        });
    }
};
