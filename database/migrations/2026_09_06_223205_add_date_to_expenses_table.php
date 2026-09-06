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
            $table->date('date')->nullable()->after('name');
        });

        // Existing rows have no real "when was this expense incurred" record
        // to draw on - the job's own created_at is the best available proxy.
        // New rows always set this explicitly (see ExpenseController and
        // SupplierExpenseController), so it's safe to backfill once here and
        // then lock the column to NOT NULL for everything going forward.
        DB::statement('
            UPDATE expenses
            INNER JOIN farm_jobs ON farm_jobs.id = expenses.farm_job_id
            SET expenses.date = DATE(farm_jobs.created_at)
            WHERE expenses.date IS NULL
        ');

        DB::statement('ALTER TABLE expenses MODIFY date DATE NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
