<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            // A sensor that fills in each measurement from its readings
            // (MetricsFromSensors). Removing the sensor turns the metric
            // back into a by-hand one.
            $table->foreignId('device_id')->nullable()->after('answer_type')->constrained()->nullOnDelete();
        });

        Schema::table('metric_measurements', function (Blueprint $table) {
            // 'sensor' when filled from the metric's sensor, 'manual' once a
            // person has changed a linked metric's measurement (so it's never
            // overwritten), null for a by-hand metric.
            $table->string('source', 10)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('metric_measurements', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('metrics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('device_id');
        });
    }
};
