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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_job_id')->constrained()->cascadeOnDelete();
            // nullOnDelete (not cascade) - mirrors expenses.supplier_id, so the
            // historical record of "we invited someone, they quoted $X" survives
            // even if the Supplier record itself is later removed.
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('requires_quote')->default(true);
            $table->string('status')->default('invited');
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
