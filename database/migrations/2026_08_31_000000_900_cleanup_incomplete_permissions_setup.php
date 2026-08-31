<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $permissionsMigrationCompleted = DB::table('migrations')
            ->where('migration', '2026_08_31_000001_create_permissions_tables')
            ->exists();

        if (
            ! $permissionsMigrationCompleted
            && Schema::hasTable('permissions')
            && ! Schema::hasTable('permission_user')
        ) {
            Schema::drop('permissions');
        }
    }

    public function down(): void
    {
        //
    }
};