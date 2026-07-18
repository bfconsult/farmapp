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
            $table->foreignId('maintenance_item_id')->nullable()->after('recurring_job_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('farm_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_item_id');
        });
    }
};
