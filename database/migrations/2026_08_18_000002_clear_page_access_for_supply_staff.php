<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supply Staff now reach every page except User Management by virtue of their
 * role, so the stored per-page access array no longer decides anything for
 * them. Clear it so the column does not describe rules that are not applied.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'supply_staff')
            ->update(['access' => null]);
    }

    public function down(): void
    {
        // The previous per-account selections cannot be reconstructed; grant the
        // full non-administrator page set instead.
        $pages = array_values(array_diff(config('access.pages', []), ['users']));

        DB::table('users')
            ->where('role', 'supply_staff')
            ->update(['access' => json_encode($pages)]);
    }
};
