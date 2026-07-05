<?php

use App\Models\FarmJob;
use App\Models\JobStatus;
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
            $table->boolean('is_default')->default(false)->after('can_book_time');
            $table->boolean('is_protected')->default(false)->after('is_default');
        });

        // Exactly one status is the default assigned to jobs created without one,
        // and it can't be renamed/reordered/deleted from Settings.
        $default = JobStatus::where('name', 'Backlog')->first();

        if (!$default) {
            $default = JobStatus::create([
                'name' => 'Backlog',
                'order' => 0,
                'can_book_time' => true,
            ]);
        }

        $default->forceFill([
            'order' => 0,
            'can_book_time' => true,
            'is_default' => true,
            'is_protected' => true,
        ])->save();

        FarmJob::whereNull('job_status_id')->update(['job_status_id' => $default->id]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_statuses', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'is_protected']);
        });
    }
};
