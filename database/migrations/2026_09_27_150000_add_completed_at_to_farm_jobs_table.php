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
        Schema::table('farm_jobs', function (Blueprint $table) {
            // When the job actually became finished - distinct from
            // updated_at, which bumps on any field change. Nothing to
            // backfill from for jobs already finished before this shipped.
            $table->timestamp('completed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
