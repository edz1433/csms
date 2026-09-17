<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A release can now be raised against the PO it draws from, so the releasing
 * form can list that PO's items and so the RIS records where the goods came
 * from. Nullable: stock is still releasable without naming a PO, and every
 * release recorded before this column existed has none.
 *
 * Deliberately NOT unique — one PO is routinely split across offices, each
 * office getting its own RIS, and the same item may appear on several of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->foreignId('delivery_id')->nullable()->after('fund_cluster_id')
                ->constrained('deliveries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_id');
        });
    }
};
