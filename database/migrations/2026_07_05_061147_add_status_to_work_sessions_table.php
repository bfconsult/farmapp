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
            // Not user-configurable (unlike JobStatus) — a fixed lifecycle: every
            // session starts as draft; existing rows default to draft too.
            $table->enum('status', ['draft', 'finalised', 'approved'])->default('draft')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_sessions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
