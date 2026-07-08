<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_ht', 15, 2);
            $table->decimal('total_tva', 15, 2);
            $table->decimal('total_ttc', 15, 2);
            $table->enum('status', ['unpaid', 'paid'])->default('unpaid');
            $table->enum('payment_method', ['virement', 'cheque', 'espece', 'carte']);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};