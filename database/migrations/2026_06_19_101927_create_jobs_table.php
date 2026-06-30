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
        Schema::create('farm_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('budget', 10, 2)->nullable();
            $table->foreignId('priority_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('job_type_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('job_status_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('property_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('farm_jobs');
    }
};
