<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['permission_id', 'user_id']);
        });
        foreach (['manage_users','manage_sellers','manage_buyers','manage_products','manage_orders','manage_payments','manage_marketplace','manage_categories','manage_reports','manage_promotions','manage_support','manage_content','manage_settings'] as $key) {
            DB::table('permissions')->insert(['key' => $key, 'name' => ucwords(str_replace('_', ' ', $key)), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down()
    {
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('permissions');
    }
};