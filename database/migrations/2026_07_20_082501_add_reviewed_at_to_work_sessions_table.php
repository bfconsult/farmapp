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
            // Set the first time a session is saved through the Edit form -
            // used to send an auto-tracked visit straight to Edit instead of
            // Show until the user has actually looked at and confirmed it
            // once (see WorkSessionController::show()).
            $table->timestamp('reviewed_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn('reviewed_at');
        });
    }
};
