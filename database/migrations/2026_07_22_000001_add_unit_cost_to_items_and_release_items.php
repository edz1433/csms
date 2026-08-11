<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Standard/current unit cost, used to price issuances on the RSMI.
            $table->decimal('unit_cost', 12, 2)->default(0)->after('on_hand_qty');
        });

        Schema::table('release_items', function (Blueprint $table) {
            // Snapshot of the item's unit cost at the moment of issue, so the
            // Report of Supplies and Materials Issued (Appendix 64) stays correct
            // even if the item's cost is edited later.
            $table->decimal('unit_cost', 12, 2)->default(0)->after('quantity');
        });

        // Backfill existing issuances from the item's current unit cost.
        DB::statement('UPDATE release_items ri JOIN items i ON i.id = ri.item_id SET ri.unit_cost = i.unit_cost');
    }

    public function down(): void
    {
        Schema::table('release_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
