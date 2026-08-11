<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            // Campuses (001–012) and offices (001–077) reuse the same numbers,
            // so a code is only unique within its type — not globally.
            $table->dropUnique('locations_code_unique');
            $table->unique(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique('locations_type_code_unique');
            $table->unique('code');
        });
    }
};
