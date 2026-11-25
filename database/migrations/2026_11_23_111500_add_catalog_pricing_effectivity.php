<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_pricing', function (Blueprint $table): void {
            $table->string('floor_type', 16)->default('fixed')->after('floor_price');
            $table->dateTime('effective_to')->nullable()->after('effective_from');
            $table->index(
                ['product_id', 'product_variant_id', 'currency', 'effective_from', 'effective_to'],
                'product_pricing_effective_idx',
            );
            $table->index('product_addon_id', 'product_pricing_addon_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_pricing', function (Blueprint $table): void {
            $table->dropIndex('product_pricing_effective_idx');
            $table->dropIndex('product_pricing_addon_idx');
            $table->dropColumn(['floor_type', 'effective_to']);
        });
    }
};
