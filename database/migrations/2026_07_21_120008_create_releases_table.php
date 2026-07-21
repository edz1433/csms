<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('ris_number')->unique()->comment('{YEAR}-{MONTH}-{LOCATION_CODE}-{SEQUENCE}');
            $table->foreignId('fund_cluster_id')->constrained('fund_clusters')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('released_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('released_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('release_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained('releases')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('account_title_id')->constrained('account_titles')->restrictOnDelete();
            $table->string('rca_code')->comment('denormalized snapshot of account_titles.rca_code at release time');
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Per-location running counter for race-free RIS sequence generation.
        Schema::create('location_release_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->unique()->constrained('locations')->cascadeOnDelete();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_release_counters');
        Schema::dropIfExists('release_items');
        Schema::dropIfExists('releases');
    }
};
