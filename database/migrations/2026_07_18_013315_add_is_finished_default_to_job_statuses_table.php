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
            // The status a job is switched to when someone taps "Finish Job" -
            // statuses are user-configurable, so this is how the app knows
            // which one means "done" (mirrors is_in_progress_default). At
            // most one status should have this set at a time.
            $table->boolean('is_finished_default')->default(false)->after('is_recurring_closed_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropColumn('is_finished_default');
        });
    }
};
