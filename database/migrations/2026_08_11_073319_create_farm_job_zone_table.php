<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_job_zone', function (Blueprint $table) {
            $table->foreignId('farm_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->primary(['farm_job_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_job_zone');
    }
};
