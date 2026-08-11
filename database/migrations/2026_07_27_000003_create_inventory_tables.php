<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A physical-count session. Only one may be open at a time — that is
        // what the QR scan pages check before they accept a count.
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('title');
            $table->string('status')->default('active')->index();   // active | closed
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // One row per item counted in a session. system_qty is the on-hand
        // figure at the moment of the first scan, so the variance stays honest
        // even if stock moves afterwards.
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained('inventory_sessions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('system_qty', 12, 2)->default(0);
            $table->decimal('counted_qty', 12, 2)->default(0);
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['inventory_session_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('inventory_sessions');
    }
};
