<?php

use App\Models\Asset;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill: one asset_locations row per asset that currently has a
        // location, carrying over its point or shape. created_by is left
        // null - there's no real actor for a value that's simply always
        // existed. created_at/updated_at are set to the asset's own
        // updated_at as the best available approximation of "when this
        // location was last set". Written via the query builder (not the
        // Eloquent model) so these timestamps can be set explicitly rather
        // than fighting Eloquent's automatic timestamp management.
        Asset::query()
            ->where(fn ($q) => $q->whereNotNull('latitude')->orWhereNotNull('shape'))
            ->get()
            ->each(function ($asset) {
                DB::table('asset_locations')->insert([
                    'asset_id' => $asset->id,
                    'latitude' => $asset->latitude,
                    'longitude' => $asset->longitude,
                    'shape' => is_array($asset->shape) ? json_encode($asset->shape) : $asset->shape,
                    'created_at' => $asset->updated_at,
                    'updated_at' => $asset->updated_at,
                ]);
            });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'shape']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('shape')->nullable();
        });

        // Intentionally does not attempt to repopulate these columns from
        // asset_locations - by this point there may be many more rows per
        // asset than the single pre-migration value, so "restore the exact
        // prior value" isn't well-defined.
    }
};
