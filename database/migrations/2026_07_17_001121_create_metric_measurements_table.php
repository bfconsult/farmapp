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
        Schema::create('metric_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metric_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the parent Metric's name/answer_type at the time
            // this measurement was created, so history survives even if the
            // Metric is later renamed or deleted (metric_id nulls out, but
            // the measurement stays meaningful on its own - mirrors how
            // FarmJob copies name/description from RecurringJob).
            $table->string('name');
            $table->enum('answer_type', ['number', 'text']);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['incomplete', 'complete'])->default('incomplete');
            $table->decimal('value_number', 12, 2)->nullable();
            $table->text('value_text')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_measurements');
    }
};
