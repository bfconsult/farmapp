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
        Schema::table('quotes', function (Blueprint $table) {
            // Marks that "Request an Invoice" was sent for this accepted
            // quote - drives Request-vs-Resend UI and gates the public
            // submit-invoice endpoint (see SupplierExpenseController).
            $table->timestamp('invoice_requested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('invoice_requested_at');
        });
    }
};
