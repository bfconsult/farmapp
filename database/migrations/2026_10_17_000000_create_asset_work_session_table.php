<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A work session's assets are the equipment *used* during it (tractor +
     * flail mower) - many per session. Maintaining an asset is recorded on
     * the job instead (FarmJob.asset_id / maintenance items), so the two
     * links never overlap. Replaces the single work_sessions.asset_id.
     */
    public function up(): void
    {
        Schema::create('asset_work_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['work_session_id', 'asset_id']);
        });

        DB::table('work_sessions')->whereNotNull('asset_id')->orderBy('id')
            ->each(function ($session) {
                DB::table('asset_work_session')->insert([
                    'work_session_id' => $session->id,
                    'asset_id' => $session->asset_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('farm_job_id')->constrained()->nullOnDelete();
        });

        // Lossy: a session with several assets keeps only its first.
        DB::table('asset_work_session')->orderBy('id')->get()
            ->unique('work_session_id')
            ->each(fn ($row) => DB::table('work_sessions')->where('id', $row->work_session_id)->update(['asset_id' => $row->asset_id]));

        Schema::dropIfExists('asset_work_session');
    }
};
