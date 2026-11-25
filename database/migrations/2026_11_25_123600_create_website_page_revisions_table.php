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
        Schema::create('website_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->json('sections');
            $table->json('seo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['website_page_id', 'revision']);
            $table->index(['tenant_id', 'website_page_id', 'created_at'], 'wpr_tenant_page_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_page_revisions');
    }
};
