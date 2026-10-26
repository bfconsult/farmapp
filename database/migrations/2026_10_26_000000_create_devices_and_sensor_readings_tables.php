<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 of docs/lorawan-ingest-spec.md. A device is registered to a
     * property by its DevEUI; each uplink is decoded into one
     * sensor_readings row per field the device reports (every field, not
     * just the ones shown - which to show lives in devices.config).
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Key into App\Services\Lorawan\DeviceModels - picks the decoder.
            $table->string('model');
            $table->string('dev_eui', 16)->unique();
            $table->json('config')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->decimal('battery', 5, 3)->nullable();
            $table->integer('last_rssi')->nullable();
            $table->decimal('last_snr', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            // Raw events are pruned after a while - the readings outlive them.
            $table->foreignId('lorawan_event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->string('metric');
            $table->decimal('value', 16, 4);
            $table->integer('rssi')->nullable();
            $table->decimal('snr', 5, 2)->nullable();
            $table->unsignedInteger('f_cnt')->nullable();

            // The gateway may retry an uplink: a retry has the same frame
            // counter and gateway timestamp. f_cnt alone isn't enough - it
            // restarts from 0 whenever the device rejoins.
            $table->unique(['device_id', 'f_cnt', 'recorded_at', 'metric']);
            $table->index(['device_id', 'metric', 'recorded_at']);
        });

        Schema::table('lorawan_events', function (Blueprint $table) {
            $table->string('dev_eui', 16)->nullable()->index()->after('event');
            $table->timestamp('processed_at')->nullable();
            $table->string('error')->nullable();
        });

        // Events captured during Phase 1 predate the dev_eui column.
        DB::table('lorawan_events')->orderBy('id')->each(function ($event) {
            $devEui = json_decode($event->body, true)['devEUI'] ?? null;
            if (is_string($devEui)) {
                DB::table('lorawan_events')->where('id', $event->id)->update(['dev_eui' => strtolower($devEui)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('lorawan_events', function (Blueprint $table) {
            $table->dropIndex(['dev_eui']);
            $table->dropColumn(['dev_eui', 'processed_at', 'error']);
        });
        Schema::dropIfExists('sensor_readings');
        Schema::dropIfExists('devices');
    }
};
