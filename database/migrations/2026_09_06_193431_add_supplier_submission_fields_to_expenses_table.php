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
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        // created_by needs to become nullable for a supplier-submitted
        // expense (no acting internal user) - same "null for the normal
        // self-entered case" precedent as WorkSession.created_by. Raw SQL
        // rather than ->nullable()->change(), since this project has no
        // doctrine/dbal dependency installed.
        DB::statement('ALTER TABLE expenses MODIFY created_by BIGINT UNSIGNED NULL');

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();

            // nullOnDelete mirrors supplier_id above it - preserve the
            // expense's history even if the quote it came from is removed.
            // Doubles as the "submitted via the supplier link" marker.
            $table->foreignId('quote_id')->nullable()->after('supplier_id')->constrained('quotes')->nullOnDelete();

            // A single invoice document (image or PDF) attached by the
            // supplier - separate from the existing photos() gallery, which
            // is image-only and force-recompresses to JPEG (would corrupt a
            // PDF). Stored untouched.
            $table->string('invoice_file')->nullable()->after('reimburse');
            $table->string('invoice_original_name')->nullable()->after('invoice_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['quote_id']);
            $table->dropColumn(['quote_id', 'invoice_file', 'invoice_original_name']);
            $table->dropForeign(['created_by']);
        });

        DB::statement('ALTER TABLE expenses MODIFY created_by BIGINT UNSIGNED NOT NULL');

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
