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
        Schema::create('reseller_hierarchy', function (Blueprint $table) {
            $table->foreignId('ancestor_reseller_id')->constrained('resellers')->restrictOnDelete();
            $table->foreignId('descendant_reseller_id')->constrained('resellers')->restrictOnDelete();
            $table->unsignedSmallInteger('depth');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->primary(['ancestor_reseller_id', 'descendant_reseller_id']);
            $table->index(['descendant_reseller_id']);
            $table->index(['ancestor_reseller_id', 'depth'], 'reseller_hierarchy_depth_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_hierarchy');
    }
};
