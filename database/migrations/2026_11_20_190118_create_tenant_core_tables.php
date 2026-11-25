<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('slug', 63)->unique();
            $table->string('name', 191);
            $table->string('legal_name', 191)->nullable();
            $table->string('type', 32)->default('reseller');
            $table->string('status', 32)->default('pending');
            $table->string('gstin', 15)->nullable();
            $table->char('currency', 3)->default('INR');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->char('locale', 5)->default('en-IN');
            $table->string('logo_path', 512)->nullable();
            $table->string('favicon_path', 512)->nullable();
            $table->dateTime('provisioned_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('terminated_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['type', 'status']);
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('host')->unique();
            $table->string('type', 16)->default('custom');
            $table->string('status', 32)->default('pending_dns');
            $table->string('verification_token', 64)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->date('ssl_expires_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('key', 128);
            $table->json('value');
            $table->string('type', 16)->default('json');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        DB::table('tenants')->insert([
            'public_id' => (string) Str::ulid(),
            'slug' => 'central',
            'name' => 'Frenchies Platform',
            'type' => 'platform',
            'status' => 'active',
            'currency' => 'INR',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en-IN',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        $centralId = DB::table('tenants')->where('slug', 'central')->value('id');
        DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $centralId]);

        Schema::table('login_activities', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });
        DB::table('login_activities')->whereNull('tenant_id')->update(['tenant_id' => $centralId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('login_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
