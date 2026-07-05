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
            // The status a job is switched to the first time time is booked against
            // it, but only when it's still sitting in the default (e.g. Backlog)
            // status — never overrides a status someone's already moved it to.
            // At most one status should have this set at a time.
            $table->boolean('is_in_progress_default')->default(false)->after('is_protected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropColumn('is_in_progress_default');
        });
    }
};
