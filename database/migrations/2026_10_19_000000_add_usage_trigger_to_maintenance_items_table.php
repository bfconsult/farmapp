<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A maintenance item can now be due by date, by hours of use, or both
     * (whichever comes first) - so the date fields become optional.
     *
     * Usage is due at usage_baseline_hours + repeat_usage_hours, where the
     * baseline is the asset's usage hours when the item was set up or its
     * last job was completed (see MaintenanceItem::resetAfterCompletion()).
     * Storing the baseline rather than the due point keeps an edit to the
     * interval correct without recalculating anything.
     */
    public function up(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->unsignedInteger('repeat_period_days')->nullable()->change();
            $table->date('next_due_date')->nullable()->change();
            $table->decimal('repeat_usage_hours', 8, 2)->nullable()->after('next_due_date');
            $table->decimal('usage_baseline_hours', 10, 2)->nullable()->after('repeat_usage_hours');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_items', function (Blueprint $table) {
            $table->dropColumn(['repeat_usage_hours', 'usage_baseline_hours']);
        });
    }
};
