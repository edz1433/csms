<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('supply_staff')->after('email')
                ->comment('administrator | supply_staff | accounting_staff');
            $table->json('access')->nullable()->after('role')
                ->comment('page keys the user may see; ignored for administrators');
            $table->boolean('is_active')->default(true)->after('access');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'access', 'is_active']);
        });
    }
};
