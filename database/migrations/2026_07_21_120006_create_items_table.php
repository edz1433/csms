<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number')->unique()->nullable()->comment('internal SKU / property code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->foreignId('account_title_id')->nullable()->constrained('account_titles')->nullOnDelete();
            $table->decimal('on_hand_qty', 12, 2)->default(0)->comment('authoritative stock level');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
