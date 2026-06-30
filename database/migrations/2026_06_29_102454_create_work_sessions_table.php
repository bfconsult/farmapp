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
        Schema::create('work_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->foreignId('farm_job_id')->nullable()->constrained('farm_jobs')->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('description')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('work_sessions');
    }
};
