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
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            // How often a new Measurement is generated for this metric.
            $table->enum('reporting_period', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly']);
            // What kind of value a Measurement records for this metric.
            $table->enum('answer_type', ['number', 'text']);
            // Paused metrics stop generating new measurements, but existing
            // MetricMeasurement instances they already created are untouched.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
