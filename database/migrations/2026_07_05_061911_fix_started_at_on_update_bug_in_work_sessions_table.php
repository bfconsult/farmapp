<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `started_at` was silently carrying `DEFAULT CURRENT_TIMESTAMP ON UPDATE
     * CURRENT_TIMESTAMP` at the database level (a MySQL legacy behavior applied
     * to the first TIMESTAMP column in a table when no explicit default is given
     * in the DDL) — meaning any UPDATE that didn't explicitly re-specify
     * started_at silently overwrote it to "now", corrupting the recorded start
     * time. Not something Laravel's migration ever asked for.
     *
     * Simply omitting a default (`MODIFY started_at TIMESTAMP NOT NULL`) is not
     * enough — this server re-applies the same implicit default/on-update magic
     * to any TIMESTAMP column left without an explicit default. Giving it a
     * harmless explicit default (never actually relied on; every insert always
     * sets started_at explicitly) is what actually suppresses it.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE work_sessions MODIFY started_at TIMESTAMP NOT NULL DEFAULT '2000-01-01 00:00:00'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE work_sessions MODIFY started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }
};
