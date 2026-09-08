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
        Schema::table('photos', function (Blueprint $table) {
            // Explicit table name - "livestock" is already plural/uncountable,
            // so constrained()'s default guess ("livestocks") would be wrong,
            // same reason the Livestock model overrides $table.
            $table->foreignId('livestock_id')->nullable()->constrained('livestock')->cascadeOnDelete()->after('expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('livestock_id');
        });
    }
};
