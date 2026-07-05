<?php

use App\Models\FarmJob;
use App\Models\Role;
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
        Schema::create('farm_job_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_job_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Backfill: every existing job becomes visible to whoever is currently
        // on its property's team, so nothing already-visible disappears.
        FarmJob::all()->each(function (FarmJob $job) {
            $teamUserIds = Role::where('property_id', $job->property_id)->pluck('user_id');
            $job->assignees()->attach($teamUserIds);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_job_user');
    }
};
