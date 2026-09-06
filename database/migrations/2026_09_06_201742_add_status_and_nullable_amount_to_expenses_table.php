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
        // A supplier submitting an invoice through their share link may
        // attach only the file, with no amount/description typed in - not
        // enough to call the expense complete. amount needs to allow that.
        // Raw SQL rather than ->nullable()->change(), since this project has
        // no doctrine/dbal dependency.
        DB::statement('ALTER TABLE expenses MODIFY amount DECIMAL(10,2) NULL');

        Schema::table('expenses', function (Blueprint $table) {
            // needs_review only ever arises from a supplier submission with
            // no amount - in-app admin/manager-created expenses always have
            // one, so they're always 'complete'. Clearing needs_review is a
            // deliberate, separate action (ExpenseController::markReviewed),
            // not automatic just because an amount got filled in later.
            $table->enum('status', ['complete', 'needs_review'])->default('complete')->after('reimburse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        DB::statement('ALTER TABLE expenses MODIFY amount DECIMAL(10,2) NOT NULL');
    }
};
