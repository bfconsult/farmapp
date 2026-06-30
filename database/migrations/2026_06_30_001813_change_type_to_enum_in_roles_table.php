<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First update existing data to valid enum values
        DB::table('roles')->where('type', 'Owner')->update(['type' => 'admin']);
        DB::table('roles')->where('type', 'Manager')->update(['type' => 'manager']);
        DB::table('roles')->where('type', 'Worker')->update(['type' => 'worker']);
    
        // Then change the column to enum
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('type', ['admin', 'manager', 'worker', 'approver'])
                  ->default('worker')
                  ->change();
        });
    }
    
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('type')->change();
        });
    }
};
