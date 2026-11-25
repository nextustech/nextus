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
        Schema::create('website_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('theme_id')->nullable()->constrained('website_themes')->nullOnDelete();
            $table->string('system_key')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->enum('page_type', [
                'homepage',
                'product_listing',
                'product_detail',
                'pricing',
                'about',
                'contact',
                'blog_list',
                'blog_post',
                'faq',
                'testimonials',
                'portfolio',
                'legal',
                'not_found',
            ]);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('parent_id')->nullable()->selfConstrained();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->text('content')->nullable();
            $table->json('sections')->nullable();
            $table->json('seo')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->unique(['tenant_id', 'system_key']);
            $table->index(['tenant_id', 'page_type']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_pages');
    }
};
