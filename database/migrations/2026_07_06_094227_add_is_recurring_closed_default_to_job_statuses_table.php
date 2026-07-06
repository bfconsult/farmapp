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
        Schema::table('job_statuses', function (Blueprint $table) {
            // The status a recurring job's instance is switched to once its
            // period ends and the next instance is generated. At most one
            // status should have this set at a time. Optional — if nothing
            // is flagged, closed instances just keep whatever status they had.
            $table->boolean('is_recurring_closed_default')->default(false)->after('is_in_progress_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropColumn('is_recurring_closed_default');
        });
    }
};
