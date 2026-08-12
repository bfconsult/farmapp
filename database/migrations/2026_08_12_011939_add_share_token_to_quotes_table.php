<?php

use App\Models\Quote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('share_token')->nullable()->unique()->after('id');
        });

        // Backfill existing quotes - new ones get one automatically (see the
        // Quote model's creating event), but pre-existing rows have none yet.
        // Previously every quote on a job shared the job's own share link, so
        // this also gives already-invited suppliers a first identified link.
        Quote::whereNull('share_token')->each(function (Quote $quote) {
            $quote->update(['share_token' => Str::random(40)]);
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
