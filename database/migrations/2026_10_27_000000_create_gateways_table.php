<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each property registers its own LoRaWAN gateway(s) and gets a key per
     * gateway, replacing the single shared LORAWAN_INGEST_KEY. The key
     * says which property an event belongs to, so events, unrecognised
     * devices and DevEUI uniqueness are all scoped to a property now.
     */
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gateway_eui', 16)->unique();
            // sha256 of the key - the key itself is only shown once.
            $table->string('key_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::table('lorawan_events', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('gateway_id')->nullable()->after('property_id')->constrained()->nullOnDelete();
            $table->string('gateway_eui', 16)->nullable()->index()->after('dev_eui');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['dev_eui']);
            $table->unique(['property_id', 'dev_eui']);
        });

        // Events so far all came through the shared key, so nothing says
        // which property they belong to. Take the gateway EUI from the body,
        // and the property from the device that's already registered.
        $devices = DB::table('devices')->pluck('property_id', 'dev_eui');
        DB::table('lorawan_events')->orderBy('id')->each(function ($event) use ($devices) {
            $gatewayEui = json_decode($event->body, true)['rxInfo'][0]['gatewayID'] ?? null;
            DB::table('lorawan_events')->where('id', $event->id)->update([
                'gateway_eui' => is_string($gatewayEui) && preg_match('/^[0-9a-fA-F]{16}$/', $gatewayEui) ? strtolower($gatewayEui) : null,
                'property_id' => $event->dev_eui ? ($devices[$event->dev_eui] ?? null) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'dev_eui']);
            $table->unique('dev_eui');
        });

        Schema::table('lorawan_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_id');
            $table->dropConstrainedForeignId('gateway_id');
            $table->dropIndex(['gateway_eui']);
            $table->dropColumn('gateway_eui');
        });

        Schema::dropIfExists('gateways');
    }
};
