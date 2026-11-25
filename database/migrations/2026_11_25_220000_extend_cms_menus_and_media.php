<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_menus', function (Blueprint $table): void {
            $table->string('target_type', 16)->default('page');
            $table->string('target')->nullable();
            $table->string('target_behavior', 8)->default('_self');
            $table->boolean('is_visible')->default(true);
            $table->softDeletes();
        });

        Schema::table('media_libraries', function (Blueprint $table): void {
            $table->foreignId('uploaded_by')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->char('checksum', 64)->nullable()->index();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('media_libraries', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropIndex(['checksum']);
            $table->dropColumn(['alt_text', 'width', 'height', 'checksum']);
            $table->dropConstrainedForeignId('uploaded_by');
        });

        Schema::table('website_menus', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['target_type', 'target', 'target_behavior', 'is_visible']);
        });
    }
};
