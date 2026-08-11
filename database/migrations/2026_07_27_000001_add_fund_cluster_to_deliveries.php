<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Optional: deliveries recorded before this column existed have none.
            $table->foreignId('fund_cluster_id')->nullable()->after('po_number')
                ->constrained('fund_clusters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fund_cluster_id');
        });
    }
};
