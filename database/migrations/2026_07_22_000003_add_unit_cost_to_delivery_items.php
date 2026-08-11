<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            // Purchase unit cost captured at receiving — feeds the Supply Ledger
            // Card (Appendix 57) receipt cost columns and keeps item cost current.
            $table->decimal('unit_cost', 12, 2)->default(0)->after('quantity');
        });

        // Backfill existing receipts from the item's current unit cost.
        DB::statement('UPDATE delivery_items di JOIN items i ON i.id = di.item_id SET di.unit_cost = i.unit_cost');
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
