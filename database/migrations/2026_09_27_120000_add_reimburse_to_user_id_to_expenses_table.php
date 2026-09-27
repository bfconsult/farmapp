<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Who should actually be paid back - distinct from created_by,
            // which is only ever whoever was logged in when the expense was
            // typed into the app (could be an admin entering it on a
            // worker's behalf). nullOnDelete, not cascadeOnDelete, same
            // reasoning as supplier_id/quote_id - losing the user shouldn't
            // corrupt expense history.
            $table->foreignId('reimburse_to_user_id')->nullable()->after('reimburse')->constrained('users')->nullOnDelete();
        });

        // Best available guess for existing rows - until now the app (and
        // the reimbursable-expenses export) implicitly assumed created_by
        // was the person to reimburse.
        DB::table('expenses')->where('reimburse', true)->whereNotNull('created_by')
            ->update(['reimburse_to_user_id' => DB::raw('created_by')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reimburse_to_user_id');
        });
    }
};
