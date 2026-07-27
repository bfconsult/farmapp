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
            // Direct link for ad-hoc work not tied to a job - a session
            // reached via farm_job_id -> FarmJob.asset_id already counts
            // toward an asset's time; this covers the sessions that have no
            // job at all. See Asset::workSessions().
            $table->foreignId('asset_id')->nullable()->after('farm_job_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
