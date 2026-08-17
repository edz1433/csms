<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspection_acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->unique()->constrained('deliveries')->cascadeOnDelete();
            $table->string('iar_number')->unique();
            $table->date('iar_date');
            $table->string('requisitioning_office')->nullable();
            $table->string('responsibility_center_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('inspection_date')->nullable();
            $table->string('inspection_officer')->nullable();
            $table->date('acceptance_date')->nullable();
            $table->string('acceptance_status')->default('complete')->comment('complete | partial');
            $table->decimal('partial_quantity', 12, 2)->nullable();
            $table->string('accepted_by')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('or_number')->nullable()->comment('Official Receipt number captured by accounting when paid');
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_acceptance_reports');
    }
};
