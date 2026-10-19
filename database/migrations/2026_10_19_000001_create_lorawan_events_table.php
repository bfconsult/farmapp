<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw events POSTed by the farm's LoRaWAN gateway (uplink/join/ack/
     * status), stored exactly as received. Phase 1 of
     * docs/lorawan-ingest-spec.md - capture real payloads before writing
     * any decoding against them.
     */
    public function up(): void
    {
        Schema::create('lorawan_events', function (Blueprint $table) {
            $table->id();
            $table->string('event');
            $table->json('body');
            $table->timestamp('received_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lorawan_events');
    }
};
