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
        Schema::table('users', function (Blueprint $table) {
            // A team member added directly (not invited) may never touch the
            // app, so they may have no email at all until someone invites them.
            $table->string('email')->nullable()->change();
            // Null until this person has actually set their own password and
            // logged in - distinguishes a "shell" record a manager created
            // purely to log time against from a real, self-serve account.
            $table->timestamp('claimed_at')->nullable()->after('email_verified_at');
        });

        // Every user that exists already registered themselves, so they're
        // all already claimed - without this backfill they'd all silently
        // drop out of summary emails and show as "Unclaimed" in the UI.
        DB::table('users')->update(['claimed_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('claimed_at');
            $table->string('email')->nullable(false)->change();
        });
    }
};
