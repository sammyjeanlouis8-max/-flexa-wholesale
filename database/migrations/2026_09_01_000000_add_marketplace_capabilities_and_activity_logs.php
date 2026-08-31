<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('capability_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capability_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['capability_id', 'user_id']);
        });
        DB::table('capabilities')->insert([
            ['key' => 'buyer', 'name' => 'Buyer', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'seller', 'name' => 'Seller', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $capabilities = DB::table('capabilities')->pluck('id', 'key');
        DB::table('users')->orderBy('id')->each(function ($user) use ($capabilities) {
            $legacy = $user->role ?: ((int) $user->role_id === 2 ? 'seller' : ((int) $user->role_id === 3 ? 'buyer' : null));
            $keys = $legacy === 'seller' ? ['buyer', 'seller'] : ($legacy === 'buyer' ? ['buyer'] : []);
            foreach ($keys as $key) {
                DB::table('capability_user')->insertOrIgnore(['capability_id' => $capabilities[$key], 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('target_type')->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down()
    {
        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('capability_user');
        Schema::dropIfExists('capabilities');
    }
};