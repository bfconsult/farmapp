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
        Schema::create('job_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_job_id')->constrained()->cascadeOnDelete();
            // e.g. "overdue_no_hours" - a growing set of reminder types will
            // share this one log table, each recorded once per job so the
            // daily check never re-sends the same reminder for the same job.
            $table->string('type');
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['farm_job_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_reminders');
    }
};
