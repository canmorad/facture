<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update invoices type enum: DEPOSIT → ACOMPTE, add SOLDE
        DB::statement("ALTER TABLE invoices MODIFY COLUMN type ENUM('STANDARD', 'ACOMPTE', 'SOLDE') NOT NULL DEFAULT 'STANDARD'");

        // Update any existing DEPOSIT records to ACOMPTE
        DB::statement("UPDATE invoices SET type = 'ACOMPTE' WHERE type = 'DEPOSIT'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN type ENUM('STANDARD', 'DEPOSIT') NOT NULL DEFAULT 'STANDARD'");
    }
};