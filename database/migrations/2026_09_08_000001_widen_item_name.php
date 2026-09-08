<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imported item lists carry long, fully-specified descriptions as the item name
 * ("A.F. 51 - Official Receipt (Accountable Form No. 51) …"), which overflowed
 * the original 255-character column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('name', 500)->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('name', 255)->change();
        });
    }
};
