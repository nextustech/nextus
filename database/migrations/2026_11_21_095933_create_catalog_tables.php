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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('slug', 191)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'position']);
            $table->index(['is_active', 'position']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('owner_tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('product_type', 32);
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->string('short_description', 500)->nullable();
            $table->mediumText('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('marketplace_status', 32)->default('unlisted');
            $table->string('price_type', 16)->default('fixed');
            $table->string('fulfillment_model', 32);
            $table->string('fulfillment_driver', 64)->nullable();
            $table->json('fulfillment_config')->nullable();
            $table->unsignedSmallInteger('promised_delivery_days')->default(5);
            $table->unsignedSmallInteger('max_revisions')->default(2);
            $table->json('license_policy')->nullable();
            $table->boolean('re_deliverable_on_renewal')->default(true);
            $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['owner_tenant_id', 'slug']);
            $table->index(['owner_tenant_id', 'status', 'created_at']);
            $table->index(['category_id', 'status']);
            $table->index(['marketplace_status', 'status']);
        });

        Schema::create('product_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('version', 16);
            $table->mediumText('changelog');
            $table->json('content_snapshot')->nullable();
            $table->string('content_storage_key', 512)->nullable();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
            $table->index(['product_id', 'released_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('active_version_id')->nullable()->after('subscription_plan_id')->constrained('product_versions')->nullOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('sku', 64)->nullable();
            $table->json('options');
            $table->smallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['product_id', 'sku']);
            $table->index(['product_id', 'position']);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('type', 16)->default('image');
            $table->string('path', 512);
            $table->string('url', 512)->nullable();
            $table->string('alt', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'position']);
        });

        Schema::create('product_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->decimal('fixed_price', 15, 2);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id']);
        });

        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('component_snapshot_price', 15, 2);
            $table->timestamps();
            $table->index(['product_package_id']);
        });

        Schema::create('product_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->decimal('price', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_addon_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_addon_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['product_id', 'product_addon_id']);
        });

        Schema::create('product_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('product_addon_id')->nullable()->constrained('product_addons')->cascadeOnDelete();
            $table->decimal('base_price', 15, 2);
            $table->decimal('floor_price', 15, 2);
            $table->char('currency', 3)->default('INR');
            $table->dateTime('effective_from');
            $table->timestamps();
            $table->index(['product_id', 'effective_from']);
        });

        Schema::create('tenant_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('grant_scope', 16)->default('tenant');
            $table->foreignId('subtree_root_tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->decimal('price_override', 15, 2)->nullable();
            $table->decimal('commission_override', 5, 2)->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('granted_at');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'product_id', 'grant_scope']);
            $table->index(['product_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('product_demos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title', 191);
            $table->string('access_type', 16)->default('open');
            $table->string('live_url', 512)->nullable();
            $table->string('credentials_username', 191)->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->string('video_url', 512)->nullable();
            $table->string('video_path', 512)->nullable();
            $table->unsignedSmallInteger('access_grant_hours')->default(24);
            $table->string('status', 32)->default('active');
            $table->dateTime('environment_created_at')->nullable();
            $table->dateTime('credentials_rotated_at')->nullable();
            $table->json('schedule_windows')->nullable();
            $table->smallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'status']);
        });

        Schema::table('product_media', function (Blueprint $table) {
            $table->foreignId('product_demo_id')->nullable()->after('product_id')->constrained('product_demos')->nullOnDelete();
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 191)->nullable();
            $table->text('body');
            $table->string('status', 32)->default('pending');
            $table->dateTime('edited_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'customer_id', 'product_id']);
            $table->index(['product_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title', 191);
            $table->string('path', 512);
            $table->string('mime_type', 125);
            $table->unsignedInteger('size_bytes')->default(0);
            $table->string('visibility', 16)->default('public');
            $table->smallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'position']);
        });

        Schema::create('demo_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_demo_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->char('token', 32)->unique();
            $table->string('state', 32)->default('active');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->dateTime('granted_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('first_viewed_at')->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'state']);
            $table->index(['product_demo_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('demo_token_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_access_token_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('event', 32);
            $table->unsignedSmallInteger('duration_seconds')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['demo_access_token_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_token_events');
        Schema::dropIfExists('demo_access_tokens');
        Schema::dropIfExists('product_documents');
        Schema::dropIfExists('product_reviews');
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_demo_id');
        });
        Schema::dropIfExists('product_demos');
        Schema::dropIfExists('tenant_products');
        Schema::dropIfExists('product_pricing');
        Schema::dropIfExists('product_addon_links');
        Schema::dropIfExists('product_addons');
        Schema::dropIfExists('package_items');
        Schema::dropIfExists('product_packages');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_variants');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_version_id');
        });
        Schema::dropIfExists('product_versions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
