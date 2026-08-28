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
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('mob_id')->nullable()->constrained('mobs')->cascadeOnDelete()->after('work_session_id');
            $table->foreignId('livestock_id')->nullable()->constrained('livestock')->cascadeOnDelete()->after('mob_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('livestock_id');
            $table->dropConstrainedForeignId('mob_id');
        });
    }
};
