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
        Schema::create('website_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->integer('position')->default(0);
            $table->foreignId('parent_id')->nullable()->selfConstrained();
            $table->string('display_name');
            $table->string('url')->nullable();
            $table->boolean('is_external')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_menus');
    }
};
