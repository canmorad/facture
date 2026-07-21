<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('quote_id')->constrained('quotes')->restrictOnDelete();
            $table->enum('status', ['DRAFT', 'FINALIZED', 'SENT', 'PAID', 'CANCELLED'])->default('DRAFT');
            $table->enum('input_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('input_value', 15, 2)->nullable();
            $table->json('deposit_ids')->nullable();
            $table->decimal('calculated_balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_invoices');
    }
};
