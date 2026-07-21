<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_titles', function (Blueprint $table) {
            $table->id();
            $table->string('rca_code')->unique()->comment('Revised Chart of Accounts code, e.g. 5-02-03-010');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_titles');
    }
};
