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
        Schema::create('website_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('template')->default('saas_modern');
            $table->string('color_primary')->default('#0066ff');
            $table->string('color_secondary')->default('#666666');
            $table->string('color_accent')->default('#ff6600');
            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('font_family')->default('system-ui');
            $table->string('font_pair')->default('system');
            $table->string('radius_preset')->default('md');
            $table->string('shadow_preset')->default('subtle');
            $table->string('banner_image')->nullable();
            $table->string('banner_text')->nullable();
            $table->text('custom_css')->nullable();
            $table->json('published_config')->nullable();
            $table->json('draft_config')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_themes');
    }
};
