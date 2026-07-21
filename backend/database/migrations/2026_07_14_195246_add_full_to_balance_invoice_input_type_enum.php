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
        // Modify the ENUM to include 'full' option
        DB::statement("ALTER TABLE balance_invoices MODIFY COLUMN input_type ENUM('percentage', 'fixed', 'full') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original ENUM values
        DB::statement("ALTER TABLE balance_invoices MODIFY COLUMN input_type ENUM('percentage', 'fixed') NULL");
    }
};
