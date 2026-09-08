<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saving an inventory seals it: a saved count can never be cast again and no
 * item created afterwards is ever placed on its sheet. It stays fully readable
 * (and exportable) — it just stops changing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            $table->foreignId('saved_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('saved_at')->nullable()->after('saved_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('saved_by');
            $table->dropColumn('saved_at');
        });
    }
};
