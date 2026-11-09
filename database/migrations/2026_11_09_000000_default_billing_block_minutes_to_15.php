<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * New accounts start on 15-minute billing blocks rather than "no
     * rounding" - a start-time picker listing every minute confused new
     * users. Still nullable, so "No rounding" stays selectable in Settings.
     * Existing users are left alone: a null there can't be told apart from
     * someone who deliberately chose no rounding.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('billing_block_minutes')->nullable()->default(15)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('billing_block_minutes')->nullable()->default(null)->change();
        });
    }
};
