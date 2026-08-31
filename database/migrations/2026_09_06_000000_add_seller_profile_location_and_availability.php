<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendors') && ! Schema::hasColumn('vendors', 'postal_code')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('postal_code', 32)->nullable()->after('address');
            });
        }

        if (! Schema::hasTable('vendor_country_availability')) {
            Schema::create('vendor_country_availability', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vendor_id');
                $table->unsignedBigInteger('country_id');
                $table->timestamps();
                $table->unique(['vendor_id', 'country_id']);
                $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
                $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('vendors') && Schema::hasTable('users') && Schema::hasTable('countries')) {
            foreach (DB::table('vendors')->get(['id', 'user_id', 'city', 'address']) as $vendor) {
                $user = DB::table('users')->where('id', $vendor->user_id)->first(['country', 'city', 'address']);
                if (!$user) {
                    continue;
                }

                $updates = [];
                if (trim((string) $vendor->city) === '' && trim((string) $user->city) !== '') {
                    $updates['city'] = $user->city;
                }
                if (trim((string) $vendor->address) === '' && trim((string) $user->address) !== '') {
                    $updates['address'] = $user->address;
                }
                if ($updates !== []) {
                    DB::table('vendors')->where('id', $vendor->id)->update($updates);
                }

                $countryIds = DB::table('product_country_availability')
                    ->join('products', 'products.id', '=', 'product_country_availability.product_id')
                    ->where('products.vendor_id', $vendor->id)
                    ->pluck('product_country_availability.country_id')
                    ->unique()
                    ->values()
                    ->all();
                if ($countryIds === [] && trim((string) $user->country) !== '') {
                    $countryId = DB::table('countries')->where('code', strtoupper(trim((string) $user->country)))->value('id');
                    $countryIds = $countryId ? [$countryId] : [];
                }
                foreach ($countryIds as $countryId) {
                    DB::table('vendor_country_availability')->insertOrIgnore([
                        'vendor_id' => $vendor->id,
                        'country_id' => $countryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_country_availability');

        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'postal_code')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropColumn('postal_code');
            });
        }
    }
};