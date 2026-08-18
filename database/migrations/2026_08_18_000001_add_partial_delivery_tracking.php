<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial deliveries: a PO is rarely received in one go — the balance often
 * arrives days or weeks later. Each delivery line now carries the ordered
 * quantity (so an outstanding balance can be shown) and the date its current
 * received quantity was last topped up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->decimal('ordered_qty', 12, 2)->nullable()->after('unit_id')
                ->comment('PO quantity for this item; null = not tracked');
            $table->date('received_at')->nullable()->after('unit_cost')
                ->comment('Date this line was last received/topped up');
        });

        // Backfill the line date from the delivery header so existing rows read
        // sensibly in the UI.
        DB::table('delivery_items')
            ->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
            ->update(['delivery_items.received_at' => DB::raw('DATE(deliveries.received_at)')]);
    }

    public function down(): void
    {
        Schema::table('delivery_items', function (Blueprint $table) {
            $table->dropColumn(['ordered_qty', 'received_at']);
        });
    }
};
