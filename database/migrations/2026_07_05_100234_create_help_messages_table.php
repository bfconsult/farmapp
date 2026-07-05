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
        Schema::create('help_messages', function (Blueprint $table) {
            $table->id();
            // Looked up from the frontend by this key, e.g. 'work-sessions.finalise-and-share'.
            $table->string('key')->unique();
            $table->string('title')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        DB::table('help_messages')->insert([
            'key' => 'work-sessions.finalise-and-share',
            'title' => 'Finalise & Share',
            'body' => "Finalising a work session locks in its date, time and job so it can no longer be edited. Once finalised, it's included in Reports and can be exported to Excel or PDF for billing.\n\nUse this page to finalise several draft sessions at once, instead of one at a time.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_messages');
    }
};
