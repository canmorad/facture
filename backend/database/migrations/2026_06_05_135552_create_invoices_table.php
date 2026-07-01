<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['DRAFT', 'SENT', 'FINALIZED', 'PAID', 'OVERDUE', 'CANCELLED'])->default('DRAFT');
            $table->date('due_date');
            $table->datetime('paid_at')->nullable();
            $table->enum('type', ['STANDARD', 'DEPOSIT'])->default('STANDARD');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
