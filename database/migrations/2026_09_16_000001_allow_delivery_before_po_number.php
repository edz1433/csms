<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receiving is now a two-step flow: the delivery header is saved first (often
 * before the PO paperwork reaches Supply) and the item lines are entered
 * against it afterwards. Both the PO number and the item lines are therefore
 * optional at creation time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('po_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('po_number')->nullable(false)->change();
        });
    }
};
