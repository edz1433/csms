<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Casting an inventory now seeds a line for every item, so a line exists before
 * anyone counts it. "Not counted" therefore has to be distinguishable from a
 * counted zero — hence the nullable count columns, with counted_at as the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_counts', function (Blueprint $table) {
            $table->decimal('counted_qty', 12, 2)->nullable()->default(null)->change();
            $table->foreignId('counted_by')->nullable()->change();
            $table->timestamp('counted_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_counts', function (Blueprint $table) {
            $table->decimal('counted_qty', 12, 2)->default(0)->change();
            $table->foreignId('counted_by')->nullable(false)->change();
            $table->timestamp('counted_at')->nullable(false)->change();
        });
    }
};
