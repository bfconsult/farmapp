<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mob_zone_history', function (Blueprint $table) {
            // The effective date the mob was moved - distinct from
            // created_at, which is only ever when the entry was recorded and
            // shouldn't change if a move is logged a few days late. Backfilled
            // from created_at below for existing rows.
            $table->date('moved_at')->nullable()->after('zone_id');
        });

        DB::table('mob_zone_history')->update(['moved_at' => DB::raw('DATE(created_at)')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mob_zone_history', function (Blueprint $table) {
            $table->dropColumn('moved_at');
        });
    }
};
