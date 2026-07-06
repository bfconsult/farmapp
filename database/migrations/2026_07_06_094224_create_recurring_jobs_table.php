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
        Schema::create('recurring_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('job_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->decimal('budget', 8, 2)->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            // How often a new instance is generated, and the date the very
            // first instance's period should start.
            $table->enum('interval', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->date('starts_on');
            // Paused templates stop generating new instances, but existing
            // FarmJob instances they already created are untouched.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_jobs');
    }
};
