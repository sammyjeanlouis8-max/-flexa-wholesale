<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('code', 2)->unique();
                $table->string('name_key');
                $table->string('name');
                $table->string('flag', 16)->nullable();
                $table->string('currency_code', 3)->nullable();
                $table->string('phone_code', 24)->nullable();
                $table->timestamps();
            });
        }

        foreach (config('countries.supported', []) as $code => $name) {
            $countryName = is_array($name) ? ($name[0] ?? $code) : $name;
            DB::table('countries')->updateOrInsert(
                ['code' => $code],
                [
                    'name_key' => \Illuminate\Support\Str::snake(str_replace(' ', '_', $countryName)),
                    'name' => $countryName,
                    'flag' => is_array($name) ? ($name[1] ?? null) : null,
                    'currency_code' => is_array($name) ? ($name[2] ?? null) : null,
                    'phone_code' => is_array($name) ? ($name[3] ?? null) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'availability_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('availability_type', 20)->default('selected')->index()->after('status');
            });
        }

        if (!Schema::hasTable('product_country_availability')) {
            Schema::create('product_country_availability', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('country_id');
                $table->unique(['product_id', 'country_id']);
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_country_availability');
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'availability_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('availability_type');
            });
        }
        Schema::dropIfExists('countries');
    }
};