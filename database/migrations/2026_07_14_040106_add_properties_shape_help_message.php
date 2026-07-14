<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('help_messages')->insert([
            'key' => 'properties.shape',
            'title' => 'Boundary & Non-Working Zone',
            'body' => "Use the polygon tool (green) to draw the property boundary, and the circle tool (amber) to mark an on-site residence, if there is one - click each corner for the boundary, or drag out a radius for the circle.\n\nTime spent in the non-working zone doesn't count as work - it splits the day into separate sessions instead of one continuous visit.\n\nThe boundary and the non-working zone are saved independently, using the buttons above.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('help_messages')->where('key', 'properties.shape')->delete();
    }
};
