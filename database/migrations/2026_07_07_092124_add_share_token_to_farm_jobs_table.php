<?php

use App\Models\FarmJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->string('share_token')->nullable()->unique()->after('id');
        });

        // Backfill existing jobs - new ones get one automatically (see the
        // FarmJob model's creating event), but pre-existing rows have none yet.
        FarmJob::whereNull('share_token')->each(function (FarmJob $job) {
            $job->update(['share_token' => Str::random(40)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
