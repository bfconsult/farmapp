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
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_job_id')->constrained()->cascadeOnDelete();
            // Nullable/nullOnDelete so deleting a template doesn't destroy a
            // job's already-completed checklist history (mirrors metric_id
            // on metric_measurements).
            $table->foreignId('checklist_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            // Snapshotted from the template at attach time.
            $table->string('name');
            $table->enum('type', ['before_start', 'at_completion']);
            $table->enum('status', ['incomplete', 'complete'])->default('incomplete');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklists');
    }
};
