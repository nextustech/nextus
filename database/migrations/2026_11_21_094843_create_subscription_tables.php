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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('family', 32);
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->unsignedInteger('version')->default(1);
            $table->string('billing_cycle', 32);
            $table->decimal('price', 15, 2)->default(0);
            $table->char('currency', 3)->default('INR');
            $table->unsignedInteger('trial_days')->default(0);
            $table->unsignedInteger('max_users')->default(0);
            $table->unsignedInteger('max_products')->default(0);
            $table->unsignedInteger('max_domains')->default(0);
            $table->unsignedInteger('max_api_keys')->default(0);
            $table->unsignedInteger('max_sub_resellers')->default(0);
            $table->unsignedInteger('ai_token_cap')->default(0);
            $table->json('features');
            $table->json('quotas');
            $table->string('status', 32)->default('draft');
            $table->timestamps();
            $table->unique(['family', 'slug', 'version']);
            $table->index(['family', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('trialing');
            $table->string('billing_cycle', 32);
            $table->decimal('snapshot_price', 15, 2);
            $table->char('snapshot_currency', 3)->default('INR');
            $table->json('snapshot_plan');
            $table->dateTime('trial_ends_at')->nullable();
            $table->dateTime('current_period_start');
            $table->dateTime('current_period_end');
            $table->dateTime('next_billing_date')->nullable();
            $table->dateTime('grace_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['tenant_id', 'subscription_plan_id', 'current_period_start'],
                'subscriptions_tenant_plan_period_unique',
            );
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->constrained()->restrictOnDelete();
            $table->string('event', 64);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('payload');
            $table->timestamps();
            $table->index(['tenant_id', 'subscription_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
