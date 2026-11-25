<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->string('published_slug')->nullable()->after('published_revision_id');
            $table->index(['tenant_id', 'published_slug']);
        });

        DB::table('website_pages')
            ->whereNotNull('published_revision_id')
            ->update(['published_slug' => DB::raw('slug')]);
    }

    public function down(): void
    {
        Schema::table('website_pages', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'published_slug']);
            $table->dropColumn('published_slug');
        });
    }
};
