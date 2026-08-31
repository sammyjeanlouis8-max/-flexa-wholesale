<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $interactionMigrationCompleted = DB::table('migrations')
            ->where('migration', '2026_09_01_000001_add_marketplace_interaction_tables')
            ->exists();

        if ($interactionMigrationCompleted) {
            return;
        }

        foreach (['reports', 'messages', 'notifications', 'cart_items', 'carts', 'favorites'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        //
    }
};