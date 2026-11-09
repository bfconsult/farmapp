<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A sensor's kind (App\Services\Lorawan\SensorKinds - what it's used
     * for, e.g. a gate) and where it is, for its map icon.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('model');
            $table->decimal('latitude', 10, 7)->nullable()->after('config');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // Every door sensor so far is on a gate.
        DB::table('devices')->where('model', 'dragino_lds02')->update(['kind' => 'gate']);
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['kind', 'latitude', 'longitude']);
        });
    }
};
