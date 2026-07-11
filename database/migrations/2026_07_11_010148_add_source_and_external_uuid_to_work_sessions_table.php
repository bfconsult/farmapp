<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            // Distinguishes sessions auto-created from the mobile app's
            // geofence-detected property visits from manually-entered ones.
            $table->string('source')->default('manual')->after('status');

            // Client-generated idempotency key for auto-tracked visit
            // uploads, so a retried sync request can't create duplicates.
            $table->uuid('external_uuid')->nullable()->unique()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn(['source', 'external_uuid']);
        });
    }
};
