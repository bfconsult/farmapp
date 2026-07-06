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
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->foreignId('recurring_job_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->date('period_start')->nullable()->after('recurring_job_id');
            $table->date('period_end')->nullable()->after('period_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_job_id');
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
