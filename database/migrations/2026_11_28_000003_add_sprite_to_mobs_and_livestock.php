<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a mob's animals are drawn in the farm view (Livestock::SPRITES):
     * set on the mob, and on an animal only where it differs. Null on a
     * mob means picked from its livestock type; on an animal, the mob's.
     */
    public function up(): void
    {
        Schema::table('mobs', function (Blueprint $table) {
            $table->string('sprite')->nullable()->after('name');
        });
        Schema::table('livestock', function (Blueprint $table) {
            $table->string('sprite')->nullable()->after('mob_id');
        });
    }

    public function down(): void
    {
        Schema::table('mobs', function (Blueprint $table) {
            $table->dropColumn('sprite');
        });
        Schema::table('livestock', function (Blueprint $table) {
            $table->dropColumn('sprite');
        });
    }
};
