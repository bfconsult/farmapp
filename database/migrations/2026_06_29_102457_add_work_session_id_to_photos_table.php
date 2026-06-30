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
            $table->foreignId('work_session_id')->nullable()->constrained('work_sessions')->onDelete('cascade')->after('job_id');
        });
    }
    
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['work_session_id']);
            $table->dropColumn('work_session_id');
        });
    }
};
